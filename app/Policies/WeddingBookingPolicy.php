<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeddingBooking;
use Illuminate\Auth\Access\Response;

class WeddingBookingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WeddingBooking $weddingBooking): bool
    {
        return $weddingBooking->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WeddingBooking $weddingBooking): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WeddingBooking $weddingBooking): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WeddingBooking $weddingBooking): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WeddingBooking $weddingBooking): bool
    {
        return false;
    }
}
