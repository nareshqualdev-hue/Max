<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\Session;

class CartSessionService
{
    /**
     * Get complete ShoppingCart session.
     */
    public function getCart(): array
    {
        return Session::get(
            'ShoppingCart',
            []
        );
    }

    /**
     * Get cart items.
     */
    public function getItems(): array
    {
        return Session::get(
            'ShoppingCart.Cart',
            []
        );
    }

    /**
     * Store complete ShoppingCart.
     */
    public function putCart(array $cart): void
    {
        Session::put(
            'ShoppingCart',
            $cart
        );
    }

    /**
     * Update a ShoppingCart value.
     */
    public function put(
        string $key,
        $value
    ): void {
        Session::put(
            'ShoppingCart.' . $key,
            $value
        );
    }

    /**
     * Read ShoppingCart value.
     */
    public function get(
        string $key,
        $default = null
    ) {
        return Session::get(
            'ShoppingCart.' . $key,
            $default
        );
    }

    /**
     * Synchronize calculated cart values.
     *
     * Exact fields will be populated by
     * CartCalculatorService.
     */
    public function syncCalculatedValues(): void
    {
        $cart = Session::get(
            'ShoppingCart',
            []
        );

        /*
         * Keep the existing session as the source of truth.
         *
         * We intentionally don't rename any existing
         * ShoppingCart keys.
         */
        Session::put(
            'ShoppingCart',
            $cart
        );
    }

    /**
     * Clear complete cart.
     */
    public function clear(): void
    {
        Session::forget(
            'ShoppingCart'
        );
    }
}