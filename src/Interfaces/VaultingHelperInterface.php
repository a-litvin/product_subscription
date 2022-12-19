<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Interfaces;

interface VaultingHelperInterface
{
    /**
     * @return int
     */
    public function getVaultId(): int;

    /**
     * @return string
     */
    public function getModuleName(): string;

    /**
     * @param int $vaultId
     *
     * @return string
     */
    public function getTokenByVaultId(int $vaultId): string;
}