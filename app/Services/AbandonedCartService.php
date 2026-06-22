<?php

namespace App\Services;

use App\Repositories\AbandonedCartRepository;
use App\Exceptions\AppError;
use App\Models\AbandonedCart;

class AbandonedCartService
{
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository
    ) {}

    public function getUserCarts(string $userId): array
    {
        return $this->abandonedCartRepository->getUserAbandonedCarts($userId)->toArray();
    }

    public function getAll(array $filters = []): array
    {
        return $this->abandonedCartRepository->paginate($filters['per_page'] ?? 15)->toArray();
    }

    public function getById(string $id, ?string $userId = null): array
    {
        $cart = $this->abandonedCartRepository->findById($id);
        if (!$cart) throw AppError::notFound('Abandoned cart not found');
        if ($userId !== null && $cart->user_id !== $userId) {
            throw AppError::notFound('Abandoned cart not found');
        }
        return $cart->toArray();
    }

    public function create(string $userId, array $data): array
    {
        $cart = AbandonedCart::create([
            'user_id' => $userId,
            'cart_data' => $data['cart_data'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'reminded' => false,
            'recovered' => false,
        ]);
        return $cart->toArray();
    }

    public function delete(string $id): void
    {
        $cart = $this->abandonedCartRepository->findById($id);
        if (!$cart) throw AppError::notFound('Abandoned cart not found');
        $this->abandonedCartRepository->delete($id);
    }

    public function getStats(): array
    {
        return $this->abandonedCartRepository->getRecoveryStats();
    }

    public function sendReminder(string $id): array
    {
        $cart = $this->abandonedCartRepository->findById($id);
        if (!$cart) throw AppError::notFound('Abandoned cart not found');

        $this->abandonedCartRepository->markReminded($id);
        // TODO: Send email/SMS reminder

        return ['message' => 'Reminder sent successfully'];
    }
}
