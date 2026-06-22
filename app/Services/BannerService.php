<?php

namespace App\Services;

use App\Repositories\BannerRepository;
use App\Exceptions\AppError;
use App\Models\Setting;

class BannerService
{
    // Valid banner types matching TypeScript VALID_TYPES
    private const VALID_TYPES = ['HERO', 'SALE', 'CATEGORY', 'POPUP', 'FEATURED', 'NEW_ARRIVAL'];

    // Snake_case DB fields that the frontend expects in camelCase
    private const CAMEL_CASE_MAP = [
        'image_url'        => 'imageUrl',
        'link_url'         => 'linkUrl',
        'display_mode'     => 'displayMode',
        'is_active'        => 'isActive',
        'text_dark'        => 'textDark',
        'show_on_mobile'   => 'showOnMobile',
        'show_on_desktop'  => 'showOnDesktop',
        'background_color' => 'backgroundColor',
        'text_color'       => 'textColor',
        'button_text'      => 'buttonText',
        'button_link'      => 'buttonLink',
        'start_date'       => 'startDate',
        'end_date'         => 'endDate',
        'created_by'       => 'createdBy',
    ];

    public function __construct(
        protected BannerRepository $bannerRepository
    ) {}

    /**
     * Convert a single banner array from snake_case to camelCase for the frontend.
     * Preserves all original keys and adds camelCase variants.
     */
    private function toCamelCase(array $banner): array
    {
        $mapped = $banner;
        foreach (self::CAMEL_CASE_MAP as $snake => $camel) {
            if (array_key_exists($snake, $banner)) {
                $mapped[$camel] = $banner[$snake];
            }
        }
        return $mapped;
    }

    /**
     * Convert a collection/model toArray result (array of banner arrays) to camelCase.
     */
    private function collectionToCamelCase(array $banners): array
    {
        return array_map(fn(array $b) => $this->toCamelCase($b), $banners);
    }

    public function getActive(): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getActive()->toArray());
    }

    public function getAll(): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->all()->toArray());
    }

    public function getById(string $id): array
    {
        $banner = $this->bannerRepository->findById($id);
        if (!$banner) throw AppError::notFound('Banner not found');
        return $this->toCamelCase($banner->toArray());
    }

    public function create(array $data): array
    {
        // Allow null image_url for TITLE_ONLY banners (they display text without an image)
        $displayMode = $data['display_mode'] ?? ($data['displayMode'] ?? 'DEFAULT');
        if (empty($data['image_url']) && $displayMode !== 'TITLE_ONLY') {
            throw AppError::validation('Image URL is required');
        }

        if (!empty($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            throw AppError::validation('Invalid banner type');
        }

        return $this->toCamelCase($this->bannerRepository->create($data)->toArray());
    }

    public function update(string $id, array $data): array
    {
        $this->bannerRepository->findByIdOrFail($id);

        if (!empty($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            throw AppError::validation('Invalid banner type');
        }

        return $this->toCamelCase($this->bannerRepository->update($id, $data)->toArray());
    }

    public function delete(string $id): void
    {
        $this->bannerRepository->findByIdOrFail($id);
        $this->bannerRepository->delete($id);
    }

    /**
     * Get active banners, optionally filtered by type.
     */
    public function getActiveBanners(?string $type = null): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getActiveByType($type)->toArray());
    }

    /**
     * Get homepage banners (all banner types grouped for the homepage).
     */
    public function getHomepageBanners(string $device = 'desktop'): array
    {
        return [
            'hero' => $this->getHeroBanners($device),
            'sale' => $this->getSaleBanners($device),
            'category' => $this->getCategoryBanners($device),
            'featured' => $this->getFeaturedBanners($device),
            'new_arrival' => $this->getNewArrivalBanners($device),
        ];
    }

    /**
     * Get hero banners — applies bannerDisplayFilter setting if set, otherwise shows all active banners.
     */
    public function getHeroBanners(string $device = 'desktop'): array
    {
        $banners = $this->bannerRepository->getByTypeAndDevice('HERO', $device)->toArray();

        // Read the global display filter setting (only filter if explicitly configured)
        $filterMode = null;
        $setting = Setting::where('key', 'bannerDisplayFilter')->first();
        if ($setting && in_array($setting->value, ['IMAGE_ONLY', 'DEFAULT'])) {
            $filterMode = $setting->value;
        }

        if ($filterMode === null) {
            // No setting configured — show ALL active banners regardless of display mode
            $filtered = $banners;
        } elseif ($filterMode === 'IMAGE_ONLY') {
            $filtered = array_values(array_filter($banners, fn($b) => ($b['display_mode'] ?? 'DEFAULT') === 'IMAGE_ONLY'));
        } else {
            // DEFAULT mode: show banners that are not IMAGE_ONLY
            $filtered = array_values(array_filter($banners, fn($b) => ($b['display_mode'] ?? 'DEFAULT') !== 'IMAGE_ONLY'));
        }

        return $this->collectionToCamelCase($filtered);
    }

    /**
     * Get sale banners.
     */
    public function getSaleBanners(string $device = 'desktop'): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getByTypeAndDevice('SALE', $device)->toArray());
    }

    /**
     * Get category banners.
     */
    public function getCategoryBanners(string $device = 'desktop'): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getByTypeAndDevice('CATEGORY', $device)->toArray());
    }

    /**
     * Get popup banners.
     */
    public function getPopupBanners(): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getByTypeAndDevice('POPUP', 'desktop')->toArray());
    }

    /**
     * Get featured banners.
     */
    public function getFeaturedBanners(string $device = 'desktop'): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getByTypeAndDevice('FEATURED', $device)->toArray());
    }

    /**
     * Get new arrival banners.
     */
    public function getNewArrivalBanners(string $device = 'desktop'): array
    {
        return $this->collectionToCamelCase($this->bannerRepository->getByTypeAndDevice('NEW_ARRIVAL', $device)->toArray());
    }

    /**
     * Toggle banner active status (Admin).
     */
    public function toggleStatus(string $id): array
    {
        $banner = $this->bannerRepository->toggleActive($id);
        return $this->toCamelCase($banner->toArray());
    }

    /**
     * Reorder banners (Admin).
     */
    public function reorder(array $bannerIds): void
    {
        if (empty($bannerIds)) {
            throw AppError::validation('Banner IDs are required');
        }
        $this->bannerRepository->reorder($bannerIds);
    }

    /**
     * Get all banners with pagination (Admin).
     */
    public function getAllBanners(array $params = []): array
    {
        return $this->bannerRepository->findMany($params);
    }
}
