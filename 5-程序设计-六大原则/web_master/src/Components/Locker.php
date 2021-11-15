<?php

namespace App\Components;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * @method static LockInterface order($customerId = null, $ttl = 300, $autoRelease = true)
 * @method static LockInterface importProducts($customerId = null, $ttl = 300, $autoRelease = true)
 * @method static LockInterface modifyPrices($customerId = null, $ttl = 300, $autoRelease = true)
 * @method static LockInterface addEditProduct($customerId = null, $ttl = 300, $autoRelease = true)
 * @method static LockInterface rma($key, $ttl = 300, $autoRelease = true)
 * @method static LockInterface couponDraw($customerId = null, $ttl = 300, $autoRelease = true)
 * @method static LockInterface sendMessage($key, $ttl = 300, $autoRelease = true)
 * @method static LockInterface autoPurchase($key, $ttl = 300, $autoRelease = true)
 * @method static LockInterface applyClaim($customerId = null, $ttl = 300, $autoRelease = true)
 * @method static LockInterface toPay($key, $ttl = 300, $autoRelease = true)
 * @method static LockInterface dropshipUploadLabelFirst($salesOrderId, $ttl = 300, $autoRelease = true)
 * @method static LockInterface checkoutConfirmCreateOrder($key, $ttl = 300, $autoRelease = true)
 * @method static LockInterface salesOrderApi($key, $ttl = 300, $autoRelease = true)
 */
class Locker
{
    public static function __callStatic($name, $arguments)
    {
        $key = $arguments[0] ?? '';
        $ttl = $arguments[1] ?? 300;
        $autoRelease = $arguments[2] ?? true;
        return static::getLock($name . $key, $ttl, $autoRelease);
    }

    /**
     * @param string $key
     * @param float|null $ttl
     * @param bool $autoRelease
     * @param string $prefix
     * @return LockInterface
     */
    protected static function getLock(string $key, ?float $ttl = 300, bool $autoRelease = true, $prefix = 'lock_')
    {
        return app(LockFactory::class)->createLock($prefix . $key, $ttl, $autoRelease);
    }
}
