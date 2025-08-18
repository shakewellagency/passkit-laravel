<?php

use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use Mockery\MockInterface;

describe('PassKitService', function () {
    beforeEach(function () {
        $this->service = app(PassKitService::class);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('can instantiate the service', function () {
        expect($this->service)->toBeInstanceOf(PassKitService::class);
    });

    it('can test connection', function () {
        // Mock the gRPC client
        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->testConnection();

        expect($result)->toBeTrue();
    });

    it('can create membership program', function () {
        $programData = [
            'name' => 'VIP Membership',
            'description' => 'Exclusive VIP benefits',
        ];

        // Mock the API response
        $mockResponse = (object) [
            'getId' => fn() => 'test_program_123',
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('createMembershipProgram')
            ->with(Mockery::type('array'))
            ->once()
            ->andReturn($mockResponse);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->createMembershipProgram($programData);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('id')
            ->and($result['id'])->toBe('test_program_123');
    });

    it('can enroll member', function () {
        $tierId = 'test_tier';
        $memberData = [
            'externalId' => 'user_123',
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'points' => 100,
        ];

        // Mock the API response
        $mockResponse = (object) [
            'getId' => fn() => 'test_member_456',
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('enrollMember')
            ->with(Mockery::type('object'))
            ->once()
            ->andReturn([$mockResponse, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->enrollMember($tierId, $memberData);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('id')
            ->and($result['id'])->toBe('test_member_456');
    });

    it('can get member', function () {
        $memberId = 'test_member_123';

        // Mock the API response
        $mockResponse = (object) [
            'getId' => fn() => $memberId,
            'getExternalId' => fn() => 'user_123',
            'getEmail' => fn() => 'test@example.com',
            'getFirstName' => fn() => 'John',
            'getLastName' => fn() => 'Doe',
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('getMember')
            ->with($memberId)
            ->once()
            ->andReturn([$mockResponse, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->getMember($memberId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('id')
            ->and($result['id'])->toBe($memberId)
            ->and($result['email'])->toBe('test@example.com');
    });

    it('can update member points', function () {
        $memberId = 'test_member_123';
        $points = 50;
        $description = 'Purchase bonus';

        // Mock the API response
        $mockResponse = (object) [
            'getId' => fn() => $memberId,
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('updateMemberPoints')
            ->with($memberId, $points, $description)
            ->once()
            ->andReturn([$mockResponse, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->updateMemberPoints($memberId, $points, $description);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('id')
            ->and($result['id'])->toBe($memberId);
    });

    it('can delete member', function () {
        $memberId = 'test_member_123';

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('deleteMember')
            ->with($memberId)
            ->once()
            ->andReturn([null, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->deleteMember($memberId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue();
    });

    it('can get pass installation package', function () {
        $memberId = 'test_member_123';

        $mockResponse = [
            'urls' => [
                'apple' => 'https://wallet.passkit.com/test',
                'google' => 'https://pay.google.com/test',
            ],
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('getPassInstallationPackage')
            ->with($memberId)
            ->once()
            ->andReturn([$mockResponse, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->getPassInstallationPackage($memberId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('urls')
            ->and($result['urls'])->toHaveKey('apple')
            ->and($result['urls'])->toHaveKey('google');
    });

    it('can send push notification', function () {
        $memberId = 'test_member_123';
        $message = 'You earned 50 points!';
        $points = 50;

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('sendPushNotification')
            ->with($memberId, $message, $points)
            ->once()
            ->andReturn([true, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->sendPushNotification($memberId, $message, $points);

        expect($result)->toBeTrue();
    });

    it('handles API errors gracefully', function () {
        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('testConnection')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->service->membershipClient = $mockClient;

        expect(function () {
            $this->service->testConnection();
        })->toThrow(\Exception::class, 'Connection failed');
    });

    it('validates member data before enrollment', function () {
        $tierId = 'test_tier';
        $invalidMemberData = [
            'externalId' => '', // Invalid: empty external ID
            'email' => 'invalid-email', // Invalid: malformed email
        ];

        expect(function () use ($tierId, $invalidMemberData) {
            $this->service->enrollMember($tierId, $invalidMemberData);
        })->toThrow(\InvalidArgumentException::class);
    });

    it('validates points amount for updates', function () {
        $memberId = 'test_member_123';
        $invalidPoints = -1000; // Invalid: negative points without proper context

        expect(function () use ($memberId, $invalidPoints) {
            $this->service->updateMemberPoints($memberId, $invalidPoints);
        })->toThrow(\InvalidArgumentException::class);
    });

    it('can create pass template', function () {
        $tierId = 'test_tier';
        $templateData = [
            'name' => 'Test Template',
            'description' => 'Test template description',
            'timezone' => 'America/Los_Angeles',
        ];

        // Mock the API response
        $mockResponse = (object) [
            'getId' => fn() => 'test_template_123',
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('createPassTemplate')
            ->with($tierId, Mockery::type('array'))
            ->once()
            ->andReturn([$mockResponse, (object) ['code' => 0]]);

        $this->service->templateClient = $mockClient;

        $result = $this->service->createPassTemplate($tierId, $templateData);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('id')
            ->and($result['id'])->toBe('test_template_123');
    });

    it('can handle gRPC status codes', function () {
        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('testConnection')
            ->once()
            ->andReturn([null, (object) ['code' => 14]]); // UNAVAILABLE status

        $this->service->membershipClient = $mockClient;

        expect(function () {
            $this->service->testConnection();
        })->toThrow(\Exception::class);
    });

    it('can enroll member with wallet pass', function () {
        $tierId = 'test_tier';
        $memberData = [
            'externalId' => 'user_123',
            'email' => 'test@example.com',
            'points' => 100,
        ];
        $userId = 1;
        $accountId = 1;

        // Mock the basic enrollment
        $mockEnrollResponse = (object) [
            'getId' => fn() => 'test_member_456',
        ];

        // Mock the installation package
        $mockPackageResponse = [
            'urls' => [
                'apple' => 'https://wallet.passkit.com/test',
                'google' => 'https://pay.google.com/test',
            ],
            'qr_codes' => [
                'apple' => 'data:image/png;base64,test',
                'google' => 'data:image/png;base64,test',
            ],
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('enrollMember')
            ->once()
            ->andReturn([$mockEnrollResponse, (object) ['code' => 0]]);
        
        $mockClient->shouldReceive('getPassInstallationPackage')
            ->once()
            ->andReturn([$mockPackageResponse, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->enrollMemberWithWalletPass($tierId, $memberData, $userId, $accountId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('member_id')
            ->and($result)->toHaveKey('wallet_pass')
            ->and($result)->toHaveKey('install_urls')
            ->and($result)->toHaveKey('qr_codes')
            ->and($result['member_id'])->toBe('test_member_456');
    });

    it('can test notification workflow', function () {
        $memberId = 'test_member_123';

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('sendPushNotification')
            ->twice() // Called twice in the workflow
            ->andReturn([true, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->testNotificationWorkflow($memberId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('welcome_notification')
            ->and($result)->toHaveKey('points_notification')
            ->and($result['welcome_notification'])->toBeTrue()
            ->and($result['points_notification'])->toBeTrue();
    });

    it('can test webhook handling', function () {
        $memberId = 'test_member_123';

        // Mock getting member details
        $mockMemberResponse = (object) [
            'getId' => fn() => $memberId,
            'getExternalId' => fn() => 'user_123',
            'getEmail' => fn() => 'test@example.com',
        ];

        // Mock points update
        $mockUpdateResponse = (object) [
            'getId' => fn() => $memberId,
        ];

        $mockClient = Mockery::mock();
        $mockClient->shouldReceive('getMember')
            ->once()
            ->andReturn([$mockMemberResponse, (object) ['code' => 0]]);
        
        $mockClient->shouldReceive('updateMemberPoints')
            ->once()
            ->andReturn([$mockUpdateResponse, (object) ['code' => 0]]);

        $this->service->membershipClient = $mockClient;

        $result = $this->service->testWebhookHandling($memberId);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('initial_state')
            ->and($result)->toHaveKey('webhook_processed')
            ->and($result)->toHaveKey('final_state')
            ->and($result['webhook_processed'])->toBeTrue();
    });

    it('can find suitable template', function () {
        // Create a test template in the database
        $template = createTestCardTemplate([
            'template_type' => 'membership',
            'is_active' => true,
        ]);

        $result = $this->service->findSuitableTemplate('membership', 1);

        expect($result)->not->toBeNull()
            ->and($result)->toBeInstanceOf(\ShakewellAgency\PassKitLaravel\Models\CardTemplate::class)
            ->and($result->id)->toBe($template->id);
    });

    it('returns null when no suitable template found', function () {
        $result = $this->service->findSuitableTemplate('nonexistent_type', 999);

        expect($result)->toBeNull();
    });

    it('can generate QR codes', function () {
        $data = 'test_qr_data';
        $size = 300;

        $result = $this->service->generateQRCode($data, $size);

        expect($result)->toBeString()
            ->and($result)->toStartWith('data:image/png;base64,');
    });

    it('handles invalid QR code data', function () {
        expect(function () {
            $this->service->generateQRCode('', 300);
        })->toThrow(\InvalidArgumentException::class);
    });

    it('validates QR code size parameter', function () {
        expect(function () {
            $this->service->generateQRCode('test', 0);
        })->toThrow(\InvalidArgumentException::class);

        expect(function () {
            $this->service->generateQRCode('test', 1001);
        })->toThrow(\InvalidArgumentException::class);
    });
});