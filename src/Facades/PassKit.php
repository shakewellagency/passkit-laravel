<?php

namespace ShakewellAgency\PassKitLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool testConnection()
 * @method static array createMembershipProgram(array $data)
 * @method static array enrollMember(string $tierId, array $data)
 * @method static array getMember(string $memberId)
 * @method static array updateMemberPoints(string $memberId, int $points, string $description = null)
 * @method static array deleteMember(string $memberId)
 * @method static array getPassInstallationPackage(string $memberId)
 * @method static bool sendPushNotification(string $memberId, string $message, int $points = 0)
 * 
 * @see \ShakewellAgency\PassKitLaravel\Services\PassKitService
 */
class PassKit extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'passkit';
    }
}