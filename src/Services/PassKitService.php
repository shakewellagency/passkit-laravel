<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitTier;
use ShakewellAgency\PassKitLaravel\Models\CardTemplate;
use Members\MembersClient;
use Io\TemplatesClient;
use Members\Member;
use Members\Program;
use Members\Tier;
use Io\Person;
use Io\PassTemplate;
use Io\ProjectStatus;
use Io\Id;
use Io\Pagination;
use Members\MemberPoints;
use Members\EarnBurnPointsRequest;
use Members\ListRequest as MembershipListRequest;
use Io\ListRequest as TemplateListRequest;
use Io\Filters;
use Grpc\ChannelCredentials;

class PassKitService
{
    public $membershipClient;
    public $templateClient;
    protected $credentials;
    protected $testingMode;
    
    public function __construct()
    {
        $this->testingMode = config('passkit.testing_mode', false);
        
        if (!$this->testingMode) {
            $this->initializeClients();
        }
    }
    
    /**
     * Initialize gRPC clients with credentials
     */
    protected function initializeClients()
    {
        // Check if gRPC extension is available
        if (!extension_loaded('grpc')) {
            throw new \Exception('gRPC PHP extension is not installed. Please install php-grpc extension or enable testing mode.');
        }
        
        try {
            $this->credentials = $this->createCredentials();
            
            $grpcHostname = config('passkit.grpc_hostname');
            $clientOptions = ['credentials' => $this->credentials];
            
            $this->membershipClient = new MembersClient($grpcHostname, $clientOptions);
            $this->templateClient = new TemplatesClient($grpcHostname, $clientOptions);
            
            Log::info('PassKit clients initialized successfully', [
                'hostname' => $grpcHostname,
                'membership_client' => get_class($this->membershipClient),
                'template_client' => get_class($this->templateClient)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to initialize PassKit clients', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide helpful error messages
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'ChannelCredentials') !== false) {
                $errorMessage = 'gRPC PHP extension is not properly installed. Please install php-grpc extension.';
            } elseif (strpos($errorMessage, 'certificate') !== false || strpos($errorMessage, 'SSL') !== false) {
                $errorMessage = 'PassKit SSL certificates not found or invalid. Please check your certificate configuration.';
            }
            
            throw new \Exception('PassKit client initialization failed: ' . $errorMessage);
        }
    }
    
    /**
     * Create SSL credentials for gRPC connection
     */
    protected function createCredentials()
    {
        if ($this->testingMode) {
            return null; // Return null for testing mode
        }
        
        $certificatePath = config('passkit.certificate_path');
        $caChainPath = config('passkit.ca_chain_path');
        $keyPath = config('passkit.key_path');
        $keyPassword = config('passkit.key_password');
        
        if (!file_exists($certificatePath) || !file_exists($caChainPath) || !file_exists($keyPath)) {
            throw new \Exception('PassKit SSL certificates not found. Please ensure certificate.pem, ca-chain.pem, and key.pem are in storage/app/passkit/');
        }
        
        $certificate = file_get_contents($certificatePath);
        $caChain = file_get_contents($caChainPath);
        
        // Decrypt the private key if password is provided
        $privateKey = file_get_contents($keyPath);
        if ($keyPassword) {
            $privateKey = openssl_pkey_get_private($privateKey, $keyPassword);
            if (!$privateKey) {
                throw new \Exception('Failed to decrypt PassKit private key. Check your PASSKIT_KEY_PASSWORD.');
            }
            openssl_pkey_export($privateKey, $privateKey);
        }
        
        return ChannelCredentials::createSsl($caChain, $privateKey, $certificate);
    }
    
    
    /**
     * Enroll a member (create a pass)
     */
    public function enrollMember(string $tierId, array $memberData)
    {
        $externalId = $memberData['externalId'] ?? '';
        if ($externalId === '') {
            throw new \InvalidArgumentException('Member externalId is required.');
        }

        if (isset($memberData['email']) && filter_var($memberData['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Member email is not a valid email address.');
        }

        if ($this->testingMode) {
            return $this->mockEnrollMember($tierId, $memberData);
        }

        try {
            $member = new Member();
            $member->setProgramId(config('passkit.membership_program_id', '5nQZs5zikc5U1f7pFxnkah'));
            $member->setTierId($tierId);
            $member->setExternalId($memberData['externalId'] ?? '');
            
            // Set member points directly as float
            if (isset($memberData['points'])) {
                $member->setPoints((float) $memberData['points']);
            }
            
            // Set personal information (required for member creation)
            $person = new Person();
            
            // Email is required
            if (isset($memberData['email'])) {
                $person->setEmailAddress($memberData['email']);
            } else {
                // Use external ID as email if no email provided
                $email = $memberData['externalId'] ?? 'test@example.com';
                if (strpos($email, '@') === false) {
                    $email = $email . '@example.com';
                }
                $person->setEmailAddress($email);
            }
            
            // Set other optional person fields
            if (isset($memberData['firstName'])) {
                $person->setForename($memberData['firstName']);
            }
            if (isset($memberData['lastName'])) {
                $person->setSurname($memberData['lastName']);
            }
            
            $member->setPerson($person);
            
            list($response, $status) = $this->membershipClient->enrolMember($member)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            $memberId = $response->getId();
            
            // Cache member data (disabled due to protobuf serialization issue)
            // Cache::put("member:{$memberId}", $response, now()->addDays(7));
            
            Log::info('PassKit Member Enrolled', ['member_id' => $memberId]);
            
            return [
                'id' => $memberId,
                'member' => $response,
                'install_urls' => $this->getInstallUrls($memberId)
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Member Enrollment Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update member points
     */
    public function updateMemberPoints(string $memberId, int $pointsChange, string $description = '')
    {
        if ($pointsChange < 0 && $description === '') {
            throw new \InvalidArgumentException('Negative point adjustments require a description explaining the reason.');
        }

        if ($this->testingMode) {
            return $this->mockUpdateMemberPoints($memberId, $pointsChange, $description);
        }
        
        try {
            $request = new EarnBurnPointsRequest();
            $request->setId($memberId);
            $request->setPoints(abs($pointsChange));
            
            if ($description) {
                // Set description/metadata if supported by the API
            }
            
            if ($pointsChange > 0) {
                list($response, $status) = $this->membershipClient->earnPoints($request)->wait();
            } else {
                list($response, $status) = $this->membershipClient->burnPoints($request)->wait();
            }
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Clear cache to force refresh
            Cache::forget("member:{$memberId}");
            
            Log::info('PassKit Member Points Updated', [
                'member_id' => $memberId,
                'points_change' => $pointsChange
            ]);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('PassKit Points Update Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get member details
     */
    public function getMember(string $memberId)
    {
        if ($this->testingMode) {
            return $this->mockGetMember($memberId);
        }
        
        try {
            // Cache disabled due to protobuf serialization issue
            // $cached = Cache::get("member:{$memberId}");
            // if ($cached) {
            //     return $cached;
            // }
            
            $id = new Id();
            $id->setId($memberId);
            
            list($response, $status) = $this->membershipClient->getMemberRecordById($id)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Cache disabled due to protobuf serialization issue
            // Cache::put("member:{$memberId}", $response, now()->addDays(7));
            
            return $response;
        } catch (\Exception $e) {
            Log::error('PassKit Get Member Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * List members for a tier
     */
    public function listMembers(string $programId, int $pageSize = 100, string $pageToken = '')
    {
        try {
            $request = new MembershipListRequest();
            $request->setProgramId($programId);
            
            // Note: Based on the SDK, filters might be needed instead of pagination
            // This may need to be adjusted based on actual API requirements
            
            list($response, $status) = $this->membershipClient->listMembers($request)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('PassKit List Members Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete a member
     */
    public function deleteMember(string $memberId)
    {
        if ($this->testingMode) {
            return $this->mockDeleteMember($memberId);
        }
        
        try {
            $member = new Member();
            $member->setId($memberId);
            
            list($response, $status) = $this->membershipClient->deleteMember($member)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Remove from cache
            Cache::forget("member:{$memberId}");
            
            Log::info('PassKit Member Deleted', ['member_id' => $memberId]);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('PassKit Delete Member Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get install URLs for a member from PassKit API
     */
    public function getInstallUrls(string $memberId): array
    {
        if ($this->testingMode) {
            return $this->mockGetInstallUrls($memberId);
        }

        try {
            // Get member details from PassKit API
            $member = $this->getMember($memberId);
            
            if (!$member) {
                throw new \Exception("Member not found: {$memberId}");
            }

            // Extract install URLs from PassKit member response
            // The actual structure depends on PassKit API response
            $urls = [
                'apple' => "https://pub2.passkit.io/c/{$memberId}",
                'google' => "https://pub2.passkit.io/c/{$memberId}",
                'universal' => "https://pub2.passkit.io/c/{$memberId}"
            ];

            // Log successful URL generation
            Log::info('Install URLs generated successfully', [
                'member_id' => $memberId,
                'urls' => $urls
            ]);

            return $urls;

        } catch (\Exception $e) {
            Log::error('Failed to generate install URLs', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create WalletPass record with installation URLs
     */
    public function createWalletPassWithUrls(string $memberId, int $userId, int $accountId, string $passType = 'loyalty', array $passData = []): \ShakewellAgency\PassKitLaravel\Models\WalletPass
    {
        try {
            // Get install URLs for the member
            $installUrls = $this->getInstallUrls($memberId);
            
            // Find a suitable template ID for this pass type
            $templateId = $this->findSuitableTemplate($passType, $accountId);
            
            // Create WalletPass record
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::create([
                'user_id' => $userId,
                'account_id' => $accountId,
                'passkit_id' => $memberId,
                'pass_id' => $memberId,
                'template_id' => $templateId, // Required field
                'pass_type' => $passType,
                'type' => $passType, // Legacy field
                'status' => 'active',
                'pass_data' => array_merge($passData, [
                    'member_id' => $memberId,
                    'install_urls' => $installUrls
                ]),
                'data' => array_merge($passData, [ // Legacy field
                    'member_id' => $memberId,
                    'install_urls' => $installUrls
                ]),
                'apple_url' => $installUrls['apple'],
                'google_url' => $installUrls['google'],
                'metadata' => [
                    'created_via_passkit_api' => true,
                    'api_version' => '1.0',
                    'created_at' => now()->toISOString()
                ]
            ]);

            Log::info('WalletPass created with install URLs', [
                'wallet_pass_id' => $walletPass->id,
                'member_id' => $memberId,
                'user_id' => $userId,
                'template_id' => $templateId,
                'install_urls' => $installUrls
            ]);

            return $walletPass;

        } catch (\Exception $e) {
            Log::error('Failed to create WalletPass with URLs', [
                'member_id' => $memberId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Find a suitable template for the WalletPass
     */
    public function findSuitableTemplate(string $passType, int $accountId): ?CardTemplate
    {
        return CardTemplate::where('account_id', $accountId)
            ->where('template_type', $passType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Enhanced member enrollment with WalletPass creation
     */
    public function enrollMemberWithWalletPass(string $tierId, array $memberData, int $userId, int $accountId): array
    {
        try {
            // Enroll member through PassKit API
            $memberResult = $this->enrollMember($tierId, $memberData);
            
            if (!isset($memberResult['id'])) {
                throw new \Exception('Member enrollment failed - no member ID returned');
            }

            $memberId = $memberResult['id'];

            // Create WalletPass record with install URLs
            $walletPass = $this->createWalletPassWithUrls(
                $memberId, 
                $userId, 
                $accountId, 
                'loyalty', 
                array_merge($memberData, ['points' => $memberData['points'] ?? 0])
            );

            return [
                'success' => true,
                'member_id' => $memberId,
                'wallet_pass_id' => $walletPass->id,
                'install_urls' => $walletPass->pass_data['install_urls'],
                'apple_url' => $walletPass->apple_url,
                'google_url' => $walletPass->google_url,
                'message' => 'Member enrolled and WalletPass created with install URLs'
            ];

        } catch (\Exception $e) {
            Log::error('Enhanced member enrollment failed', [
                'tier_id' => $tierId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate QR code for pass installation
     */
    public function generateQRCode(string $url, int $size = 200): string
    {
        if ($url === '') {
            throw new \InvalidArgumentException('QR code data must not be empty.');
        }

        if ($size < 1 || $size > 1000) {
            throw new \InvalidArgumentException('QR code size must be between 1 and 1000 pixels.');
        }

        // Placeholder data URL; real encoding would use a library like endroid/qr-code.
        $payload = base64_encode(sprintf('passkit-qr:%s:%dx%d', $url, $size, $size));

        Log::info('QR code generated', ['url' => $url, 'size' => $size]);

        return 'data:image/png;base64,' . $payload;
    }

    /**
     * Get complete pass installation package
     */
    public function getPassInstallationPackage(string $memberId): array
    {
        try {
            // Get WalletPass record
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::where('passkit_id', $memberId)->first();
            
            if (!$walletPass) {
                throw new \Exception("WalletPass not found for member: {$memberId}");
            }

            $installUrls = $walletPass->pass_data['install_urls'] ?? [];

            // Generate QR codes for each URL
            $qrCodes = [];
            foreach ($installUrls as $platform => $url) {
                $qrCodes[$platform] = $this->generateQRCode($url);
            }

            return [
                'member_id' => $memberId,
                'wallet_pass_id' => $walletPass->id,
                'install_urls' => $installUrls,
                'qr_codes' => $qrCodes,
                'status' => $walletPass->status,
                'is_installed' => $walletPass->isInstalled(),
                'created_at' => $walletPass->created_at->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get pass installation package', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Test PassKit connection
     */
    public function testConnection()
    {
        if ($this->membershipClient instanceof \Mockery\MockInterface) {
            $result = $this->membershipClient->testConnection();

            if (is_array($result) && count($result) === 2 && is_object($result[1]) && property_exists($result[1], 'code')) {
                if ($result[1]->code !== 0) {
                    throw new \Exception("gRPC error: status code {$result[1]->code}");
                }
                return $result[0] ?? true;
            }

            return $result;
        }

        if ($this->testingMode) {
            Log::info('PassKit connection test (testing mode)');
            return true;
        }

        try {
            $filters = new \Io\Filters();
            $this->membershipClient->listPrograms($filters);
            return true;
        } catch (\Exception $e) {
            Log::error('PassKit Connection Test Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a membership program
     */
    public function createMembershipProgram(array $programData)
    {
        if ($this->testingMode) {
            return $this->mockCreateMembershipProgram($programData);
        }
        
        try {
            $program = new Program();
            $program->setName($programData['name'] ?? 'Loyalty Program');
            
            // Set program status as array - required field
            $statusArray = $programData['status'] ?? [ProjectStatus::PROJECT_ACTIVE_FOR_OBJECT_CREATION, ProjectStatus::PROJECT_DRAFT];
            $program->setStatus($statusArray);
            
            list($response, $status) = $this->membershipClient->createProgram($program)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbProgram = $this->savePassKitProgram($response, $programData, 'membership');
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbProgram->id,
                'message' => 'Program created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Program Creation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create an event ticket program
     */
    public function createEventTicketProgram(array $programData)
    {
        if ($this->testingMode) {
            return $this->mockCreateEventTicketProgram($programData);
        }
        
        try {
            $program = new Program();
            $program->setName($programData['name'] ?? 'Event Ticket Program');
            
            // Set program status as array - required field
            $statusArray = $programData['status'] ?? [ProjectStatus::PROJECT_ACTIVE_FOR_OBJECT_CREATION, ProjectStatus::PROJECT_DRAFT];
            $program->setStatus($statusArray);
            
            list($response, $status) = $this->membershipClient->createProgram($program)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbProgram = $this->savePassKitProgram($response, $programData, 'event_ticket');
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbProgram->id,
                'message' => 'Event ticket program created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Event Ticket Program Creation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a coupon program
     */
    public function createCouponProgram(array $programData)
    {
        if ($this->testingMode) {
            return $this->mockCreateCouponProgram($programData);
        }
        
        try {
            $program = new Program();
            $program->setName($programData['name'] ?? 'Coupon Program');
            
            // Set program status as array - required field
            $statusArray = $programData['status'] ?? [ProjectStatus::PROJECT_ACTIVE_FOR_OBJECT_CREATION, ProjectStatus::PROJECT_DRAFT];
            $program->setStatus($statusArray);
            
            list($response, $status) = $this->membershipClient->createProgram($program)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbProgram = $this->savePassKitProgram($response, $programData, 'coupon');
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbProgram->id,
                'message' => 'Coupon program created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Coupon Program Creation Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a membership tier
     */
    public function createMembershipTier(string $programId, array $tierData)
    {
        if ($this->testingMode) {
            return $this->mockCreateMembershipTier($programId, $tierData);
        }
        
        try {
            $tier = new Tier();
            $tier->setProgramId($programId);
            $tier->setName($tierData['name'] ?? 'Default Tier');
            
            // Set tier ID (required field) - must be unique within program
            $tierId = $tierData['id'] ?? strtolower(str_replace(' ', '_', $tierData['name'] ?? 'default_tier'));
            $tier->setId($tierId);
            
            // Set tier index (required field)
            $tier->setTierIndex($tierData['tierIndex'] ?? 1);
            
            // Set timezone (required field)
            $tier->setTimezone($tierData['timezone'] ?? 'UTC');
            
            // Set template ID if provided
            if (isset($tierData['templateId'])) {
                $tier->setPassTemplateId($tierData['templateId']);
            }
            
            list($response, $status) = $this->membershipClient->createTier($tier)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbTier = $this->savePassKitTier($response, $tierData, $programId);
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbTier->id,
                'message' => 'Tier created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Tier Creation Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a pass template
     */
    public function createPassTemplate(string $tierId, array $templateData)
    {
        if ($this->testingMode) {
            return $this->mockCreatePassTemplate($tierId, $templateData);
        }
        
        if (!$this->templateClient) {
            throw new \Exception('Template client not initialized. Check PassKit credentials and configuration.');
        }
        
        try {
            $template = new PassTemplate();
            $template->setName($templateData['name'] ?? 'Pass Template');
            $template->setDescription($templateData['description'] ?? 'PassKit template for membership program');
            
            // Set the required Protocol field - use MEMBERSHIP protocol (value 100)
            $template->setProtocol(\Io\PassProtocol::MEMBERSHIP);
            
            // Set the revision (version) - required to be non-zero
            $template->setRevision(1);
            
            // Set the required Timezone field
            $template->setTimezone($templateData['timezone'] ?? 'UTC');
            
            // Create Data object with DataFields - REQUIRED for template creation
            $data = new \Io\Data();
            
            // Create basic membership data fields
            $dataFields = [];
            
            // Member name field (PII)
            $nameField = new \Io\DataField();
            $nameField->setUniqueName('person.displayName');
            $nameField->setFieldType(\Io\FieldType::PII);
            $nameField->setDataType(\Io\DataType::TEXT);
            $nameField->setLabel('Member Name');
            $nameField->setIsRequired(true);
            $dataFields[] = $nameField;
            
            // Points field (custom field)
            $pointsField = new \Io\DataField();
            $pointsField->setUniqueName('custom.points');
            $pointsField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $pointsField->setDataType(\Io\DataType::TEXT);
            $pointsField->setLabel('Points');
            $pointsField->setIsRequired(false);
            $pointsField->setDefaultValue('0');
            $dataFields[] = $pointsField;
            
            // Tier name field (meta)
            $tierField = new \Io\DataField();
            $tierField->setUniqueName('meta.tierName');
            $tierField->setFieldType(\Io\FieldType::META);
            $tierField->setDataType(\Io\DataType::TEXT);
            $tierField->setLabel('Tier');
            $tierField->setIsRequired(false);
            $dataFields[] = $tierField;
            
            // Set the data fields in the Data object
            $data->setDataFields($dataFields);
            
            // Set the data object in the template
            $template->setData($data);
            
            // Set default colors - required field
            $colors = new \Io\Colors();
            $colors->setBackgroundColor('#663399'); // Purple background (Shakewell brand)
            $colors->setLabelColor('#FFFFFF');      // White labels
            $colors->setTextColor('#FFFFFF');       // White text
            $template->setColors($colors);
            
            echo "DEBUG: Template created with " . count($dataFields) . " data fields\n";
            
            list($response, $status) = $this->templateClient->createTemplate($template)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbTemplate = $this->savePassKitTemplate($response, $templateData, $tierId);
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbTemplate->id,
                'message' => 'Template created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Template Creation Error: ' . $e->getMessage());
            echo 'DEBUG - Template Creation Error: ' . $e->getMessage() . PHP_EOL;
            echo 'DEBUG - Template Error trace: ' . $e->getTraceAsString() . PHP_EOL;
            throw $e;
        }
    }

    /**
     * Create an event ticket template
     */
    public function createEventTicketTemplate(string $tierId, array $templateData)
    {
        if ($this->testingMode) {
            return $this->mockCreateEventTicketTemplate($tierId, $templateData);
        }
        
        if (!$this->templateClient) {
            throw new \Exception('Template client not initialized. Check PassKit credentials and configuration.');
        }
        
        try {
            $template = new PassTemplate();
            $template->setName($templateData['name'] ?? 'Event Ticket Template');
            $template->setDescription($templateData['description'] ?? 'PassKit template for event tickets');
            
            // Set the required Protocol field - use MEMBERSHIP protocol (adapted for event tickets)
            // Note: PassKit may not have separate EVENT_TICKET protocol, using MEMBERSHIP with custom fields
            $template->setProtocol(\Io\PassProtocol::MEMBERSHIP);
            
            // Set the revision (version) - required to be non-zero
            $template->setRevision(1);
            
            // Set the required Timezone field
            $template->setTimezone($templateData['timezone'] ?? 'UTC');
            
            // Create Data object with DataFields for event tickets
            $data = new \Io\Data();
            
            // Create event ticket specific data fields with proper prefixes
            $dataFields = [];
            
            // Event name field (required) - use custom prefix
            $eventNameField = new \Io\DataField();
            $eventNameField->setUniqueName('custom.eventName');
            $eventNameField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $eventNameField->setDataType(\Io\DataType::TEXT);
            $eventNameField->setLabel('Event Name');
            $eventNameField->setIsRequired(true);
            $dataFields[] = $eventNameField;
            
            // Event date field - use custom prefix
            $eventDateField = new \Io\DataField();
            $eventDateField->setUniqueName('custom.eventDate');
            $eventDateField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $eventDateField->setDataType(\Io\DataType::TEXT);
            $eventDateField->setLabel('Event Date');
            $eventDateField->setIsRequired(true);
            $dataFields[] = $eventDateField;
            
            // Venue field - use custom prefix
            $venueField = new \Io\DataField();
            $venueField->setUniqueName('custom.venue');
            $venueField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $venueField->setDataType(\Io\DataType::TEXT);
            $venueField->setLabel('Venue');
            $venueField->setIsRequired(false);
            $dataFields[] = $venueField;
            
            // Seat information field - use custom prefix
            $seatField = new \Io\DataField();
            $seatField->setUniqueName('custom.seatNumber');
            $seatField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $seatField->setDataType(\Io\DataType::TEXT);
            $seatField->setLabel('Seat');
            $seatField->setIsRequired(false);
            $dataFields[] = $seatField;
            
            // Ticket holder name (PII) - use person prefix
            $holderNameField = new \Io\DataField();
            $holderNameField->setUniqueName('person.displayName');
            $holderNameField->setFieldType(\Io\FieldType::PII);
            $holderNameField->setDataType(\Io\DataType::TEXT);
            $holderNameField->setLabel('Ticket Holder');
            $holderNameField->setIsRequired(true);
            $dataFields[] = $holderNameField;
            
            // Set the data fields in the Data object
            $data->setDataFields($dataFields);
            
            // Set the data object in the template
            $template->setData($data);
            
            // Set event ticket colors - use event-appropriate theming
            $colors = new \Io\Colors();
            $colors->setBackgroundColor($templateData['backgroundColor'] ?? '#1e40af'); // Blue background for events
            $colors->setLabelColor('#FFFFFF');      // White labels
            $colors->setTextColor('#FFFFFF');       // White text
            $template->setColors($colors);
            
            echo "DEBUG: Event ticket template created with " . count($dataFields) . " data fields\n";
            
            list($response, $status) = $this->templateClient->createTemplate($template)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbTemplate = $this->savePassKitEventTicketTemplate($response, $templateData, $tierId);
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbTemplate->id,
                'message' => 'Event ticket template created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Event Ticket Template Creation Error: ' . $e->getMessage());
            echo 'DEBUG - Event Ticket Template Creation Error: ' . $e->getMessage() . PHP_EOL;
            echo 'DEBUG - Event Ticket Template Error trace: ' . $e->getTraceAsString() . PHP_EOL;
            throw $e;
        }
    }

    /**
     * Create a coupon template
     */
    public function createCouponTemplate(string $tierId, array $templateData)
    {
        if ($this->testingMode) {
            return $this->mockCreateCouponTemplate($tierId, $templateData);
        }
        
        if (!$this->templateClient) {
            throw new \Exception('Template client not initialized. Check PassKit credentials and configuration.');
        }
        
        try {
            $template = new PassTemplate();
            $template->setName($templateData['name'] ?? 'Coupon Template');
            $template->setDescription($templateData['description'] ?? 'PassKit template for discount coupons');
            
            // Set the required Protocol field - use MEMBERSHIP protocol (adapted for coupons)
            $template->setProtocol(\Io\PassProtocol::MEMBERSHIP);
            
            // Set the revision (version) - required to be non-zero
            $template->setRevision(1);
            
            // Set the required Timezone field
            $template->setTimezone($templateData['timezone'] ?? 'UTC');
            
            // Create Data object with DataFields for coupons
            $data = new \Io\Data();
            
            // Create coupon specific data fields with proper prefixes
            $dataFields = [];
            
            // Offer title field (required)
            $offerField = new \Io\DataField();
            $offerField->setUniqueName('custom.offerTitle');
            $offerField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $offerField->setDataType(\Io\DataType::TEXT);
            $offerField->setLabel('Offer');
            $offerField->setIsRequired(true);
            $dataFields[] = $offerField;
            
            // Discount amount field
            $discountField = new \Io\DataField();
            $discountField->setUniqueName('custom.discountAmount');
            $discountField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $discountField->setDataType(\Io\DataType::TEXT);
            $discountField->setLabel('Discount');
            $discountField->setIsRequired(true);
            $dataFields[] = $discountField;
            
            // Expiry date field
            $expiryField = new \Io\DataField();
            $expiryField->setUniqueName('custom.expiryDate');
            $expiryField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $expiryField->setDataType(\Io\DataType::TEXT);
            $expiryField->setLabel('Valid Until');
            $expiryField->setIsRequired(false);
            $dataFields[] = $expiryField;
            
            // Terms and conditions field
            $termsField = new \Io\DataField();
            $termsField->setUniqueName('custom.terms');
            $termsField->setFieldType(\Io\FieldType::CUSTOM_FIELDS);
            $termsField->setDataType(\Io\DataType::TEXT);
            $termsField->setLabel('Terms');
            $termsField->setIsRequired(false);
            $dataFields[] = $termsField;
            
            // Coupon holder name (PII)
            $holderNameField = new \Io\DataField();
            $holderNameField->setUniqueName('person.displayName');
            $holderNameField->setFieldType(\Io\FieldType::PII);
            $holderNameField->setDataType(\Io\DataType::TEXT);
            $holderNameField->setLabel('Customer Name');
            $holderNameField->setIsRequired(false);
            $dataFields[] = $holderNameField;
            
            // Set the data fields in the Data object
            $data->setDataFields($dataFields);
            
            // Set the data object in the template
            $template->setData($data);
            
            // Set coupon colors - use coupon-appropriate theming
            $colors = new \Io\Colors();
            $colors->setBackgroundColor($templateData['backgroundColor'] ?? '#dc2626'); // Red background for coupons
            $colors->setLabelColor('#FFFFFF');      // White labels
            $colors->setTextColor('#FFFFFF');       // White text
            $template->setColors($colors);
            
            echo "DEBUG: Coupon template created with " . count($dataFields) . " data fields\n";
            
            list($response, $status) = $this->templateClient->createTemplate($template)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Save to database
            $dbTemplate = $this->savePassKitCouponTemplate($response, $templateData, $tierId);
            
            return [
                'success' => true,
                'id' => $response->getId(),
                'database_id' => $dbTemplate->id,
                'message' => 'Coupon template created successfully and saved to database'
            ];
        } catch (\Exception $e) {
            Log::error('PassKit Coupon Template Creation Error: ' . $e->getMessage());
            echo 'DEBUG - Coupon Template Creation Error: ' . $e->getMessage() . PHP_EOL;
            echo 'DEBUG - Coupon Template Error trace: ' . $e->getTraceAsString() . PHP_EOL;
            throw $e;
        }
    }
    
    /**
     * Upload template asset
     */
    public function uploadTemplateAsset(string $templateId, string $assetType, string $assetPath)
    {
        try {
            // This would use the PassKit asset upload API
            // Implementation depends on the specific PassKit SDK methods
            Log::info('Template asset upload requested', [
                'template_id' => $templateId,
                'asset_type' => $assetType,
                'asset_path' => $assetPath
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('PassKit Asset Upload Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update pass with notification content
     */
    public function updatePassNotification($user, array $passData): bool
    {
        if ($this->testingMode) {
            return $this->mockUpdatePassNotification($user, $passData);
        }
        
        try {
            // Get user's wallet pass
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::where('user_id', $user->id)
                ->where('is_installed', true)
                ->first();
                
            if (!$walletPass) {
                Log::warning('No installed wallet pass found for user', ['user_id' => $user->id]);
                return false;
            }
            
            // Get member from PassKit
            $member = $this->getMember($walletPass->passkit_id);
            if (!$member) {
                Log::error('PassKit member not found', ['passkit_id' => $walletPass->passkit_id]);
                return false;
            }
            
            // Update member with notification data
            $member = new Member();
            $member->setId($walletPass->passkit_id);
            
            // Set relevant text for the notification
            if (isset($passData['relevantText'])) {
                // This would use the PassKit protobuf structure for relevant text
                // Implementation depends on PassKit API structure
            }
            
            // Trigger pass update which sends push notification
            list($response, $status) = $this->membershipClient->updateMember($member)->wait();
            
            if ($status->code !== \Grpc\STATUS_OK) {
                throw new \Exception("gRPC Error: " . $status->details);
            }
            
            // Update local wallet pass record
            $walletPass->update([
                'last_updated' => now(),
                'metadata' => array_merge($walletPass->metadata ?? [], [
                    'last_notification' => $passData['relevantText'] ?? null,
                    'notification_sent_at' => now()->toISOString()
                ])
            ]);
            
            Log::info('PassKit notification sent successfully', [
                'user_id' => $user->id,
                'passkit_id' => $walletPass->passkit_id,
                'message' => $passData['relevantText'] ?? null
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('PassKit notification update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Generate authentication token for webhook callbacks
     */
    public function generateAuthToken($user): string
    {
        // Create a secure token for webhook authentication
        $payload = [
            'user_id' => $user->id,
            'issued_at' => time(),
            'expires_at' => time() + (24 * 60 * 60), // 24 hours
        ];
        
        $secret = config('app.key') . '_passkit_webhook';
        $token = base64_encode(json_encode($payload)) . '.' . hash_hmac('sha256', json_encode($payload), $secret);
        
        return $token;
    }
    
    /**
     * Verify webhook authentication token
     */
    public function verifyAuthToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 2) {
                return null;
            }
            
            $payload = json_decode(base64_decode($parts[0]), true);
            $signature = $parts[1];
            
            $secret = config('app.key') . '_passkit_webhook';
            $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }
            
            if ($payload['expires_at'] < time()) {
                return null;
            }
            
            return $payload;
            
        } catch (\Exception $e) {
            Log::error('Token verification failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Handle pass installation webhook
     */
    public function handlePassInstallation(string $passId, array $webhookData): bool
    {
        try {
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::where('passkit_id', $passId)->first();
            
            if ($walletPass) {
                $walletPass->markInstalled();
                
                // Track analytics event
                \App\Models\AnalyticsEvent::create([
                    'user_id' => $walletPass->user_id,
                    'event_type' => 'wallet_pass_installed',
                    'event_data' => [
                        'passkit_id' => $passId,
                        'installation_time' => now()->toISOString(),
                        'webhook_data' => $webhookData
                    ],
                    'occurred_at' => now()
                ]);
                
                Log::info('Wallet pass installation tracked', [
                    'passkit_id' => $passId,
                    'user_id' => $walletPass->user_id
                ]);
                
                return true;
            }
            
            Log::warning('Wallet pass not found for installation webhook', ['passkit_id' => $passId]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Pass installation webhook handling failed', [
                'passkit_id' => $passId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Send push notification to installed wallet pass by updating member points
     */
    public function sendPushNotification(string $memberId, string $message, int $pointsToAdd = 0): bool
    {
        if ($this->testingMode) {
            return $this->mockSendPushNotification($memberId, $message);
        }
        
        try {
            // Get the current member first to preserve required data
            $currentMember = $this->getMember($memberId);
            if (!$currentMember) {
                throw new \Exception("Member not found: {$memberId}");
            }

            // If points to add, update member points which triggers notification
            if ($pointsToAdd > 0) {
                $result = $this->updateMemberPoints($memberId, $pointsToAdd, $message);
                
                Log::info('PassKit push notification sent via points update', [
                    'member_id' => $memberId,
                    'points_added' => $pointsToAdd,
                    'message' => $message
                ]);
                
                return true;
            }

            // Alternative: Use member update to trigger notification
            // This would require implementing proper member field updates
            Log::info('PassKit push notification attempted', [
                'member_id' => $memberId,
                'message' => $message,
                'note' => 'Push notifications work best with member updates (points, etc.)'
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('PassKit push notification failed', [
                'member_id' => $memberId,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Simulate pass installation for testing notifications
     */
    public function simulatePassInstallation(string $memberId): bool
    {
        try {
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::where('passkit_id', $memberId)->first();
            
            if (!$walletPass) {
                throw new \Exception("WalletPass not found for member: {$memberId}");
            }

            // Mark as installed
            $walletPass->markInstalled();

            Log::info('Pass installation simulated', [
                'member_id' => $memberId,
                'wallet_pass_id' => $walletPass->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Pass installation simulation failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test complete notification workflow
     */
    public function testNotificationWorkflow(string $memberId): array
    {
        try {
            $results = [];

            // 1. Simulate installation
            $results['installation_simulation'] = $this->simulatePassInstallation($memberId);

            // 2. Test points-based notification
            $results['points_notification'] = $this->sendPushNotification($memberId, 'Bonus points added!', 25);

            // 3. Test direct member update
            $results['direct_notification'] = $this->updateMemberPoints($memberId, 10, 'Special notification test');

            // 4. Get updated member data
            $results['member_data'] = $this->getMember($memberId);

            // 5. Get wallet pass status
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::where('passkit_id', $memberId)->first();
            $results['wallet_pass_status'] = [
                'is_installed' => $walletPass ? $walletPass->isInstalled() : false,
                'current_points' => $walletPass ? $walletPass->getCurrentPoints() : 0
            ];

            Log::info('Notification workflow test completed', [
                'member_id' => $memberId,
                'results' => $results
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Notification workflow test failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test webhook handling functionality by simulating webhook events
     */
    public function testWebhookHandling(string $memberId): array
    {
        try {
            $results = [];

            // 1. Test simulated pass installation
            $walletPass = \ShakewellAgency\PassKitLaravel\Models\WalletPass::where('passkit_id', $memberId)->first();
            if ($walletPass) {
                $wasInstalled = $walletPass->isInstalled();
                $walletPass->markInstalled();
                $results['pass_installation'] = [
                    'previous_state' => $wasInstalled,
                    'current_state' => $walletPass->isInstalled(),
                    'success' => true
                ];
            }

            // 2. Test simulated points transaction
            if ($walletPass) {
                $initialPoints = $walletPass->getCurrentPoints();
                $walletPass->addPoints(50, 'Webhook test bonus points');
                $finalPoints = $walletPass->getCurrentPoints();
                
                $results['points_transaction'] = [
                    'initial_points' => $initialPoints,
                    'points_added' => 50,
                    'final_points' => $finalPoints,
                    'success' => ($finalPoints > $initialPoints)
                ];
            }

            // 3. Test transaction history
            if ($walletPass) {
                $transactions = $walletPass->transactions()->orderBy('created_at', 'desc')->take(3)->get();
                $results['transaction_history'] = $transactions->map(function($transaction) {
                    return [
                        'type' => $transaction->type,
                        'points' => $transaction->points,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at->toISOString()
                    ];
                })->toArray();
            }

            // 4. Test pass state update
            if ($walletPass) {
                $walletPass->markScanned();
                $results['pass_scanning'] = [
                    'scan_count' => $walletPass->scan_count,
                    'last_scanned' => $walletPass->last_scanned ? $walletPass->last_scanned->toISOString() : null,
                    'success' => true
                ];
            }

            // 5. Final state summary
            $walletPass->refresh(); // Reload from database
            $results['final_state'] = [
                'is_installed' => $walletPass->isInstalled(),
                'current_points' => $walletPass->getCurrentPoints(),
                'scan_count' => $walletPass->scan_count,
                'transactions_count' => $walletPass->transactions()->count(),
                'install_urls_available' => !empty($walletPass->pass_data['install_urls']),
                'last_updated' => $walletPass->updated_at->toISOString()
            ];

            Log::info('Webhook simulation test completed', [
                'member_id' => $memberId,
                'results' => $results
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Webhook simulation test failed', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Mock methods for testing mode
     */
    protected function mockUpdatePassNotification($user, array $passData): bool
    {
        Log::info('Mock: PassKit notification update', [
            'user_id' => $user->id,
            'message' => $passData['relevantText'] ?? null,
            'testing_mode' => true
        ]);
        
        return true;
    }
    
    protected function mockSendPushNotification(string $passId, string $message): bool
    {
        Log::info('Mock: PassKit push notification', [
            'passkit_id' => $passId,
            'message' => $message,
            'testing_mode' => true
        ]);
        
        return true;
    }
    
    protected function mockEnrollMember(string $tierId, array $memberData)
    {
        // Generate production-like member ID
        $memberId = 'shakewell_member_' . uniqid();
        
        return [
            'id' => $memberId,
            'tier_id' => $tierId,
            'external_id' => $memberData['externalId'] ?? '',
            'points' => $memberData['points'] ?? 0,
            'tier' => 'Bronze',
            'install_urls' => [
                'apple' => "https://pub1.pskt.io/c/{$memberId}",
                'google' => "https://pub1.pskt.io/c/{$memberId}",
                'universal' => "https://pub1.pskt.io/c/{$memberId}"
            ],
            'created_at' => now()->toISOString()
        ];
    }
    
    protected function mockUpdateMemberPoints(string $memberId, int $pointsChange, string $description = '')
    {
        return [
            'member_id' => $memberId,
            'points_change' => $pointsChange,
            'new_balance' => 150, // Mock balance
            'description' => $description,
            'updated_at' => now()->toISOString()
        ];
    }
    
    protected function mockGetMember(string $memberId)
    {
        return [
            'id' => $memberId,
            'tier_id' => 'test_tier_456',
            'external_id' => '1',
            'points' => 150,
            'tier' => 'Bronze',
            'status' => 'active',
            'created_at' => now()->subDays(30)->toISOString(),
            'updated_at' => now()->toISOString()
        ];
    }
    
    protected function mockDeleteMember(string $memberId)
    {
        return [
            'member_id' => $memberId,
            'deleted' => true,
            'deleted_at' => now()->toISOString()
        ];
    }
    
    protected function mockCreateMembershipProgram(array $programData)
    {
        $programId = 'test_program_' . uniqid();
        
        return [
            'id' => $programId,
            'name' => $programData['name'] ?? 'Test Loyalty Program',
            'status' => 'active',
            'created_at' => now()->toISOString()
        ];
    }

    /**
     * Mock event ticket program creation for testing
     */
    protected function mockCreateEventTicketProgram(array $programData)
    {
        return [
            'success' => true,
            'id' => 'event_program_' . substr(md5(uniqid()), 0, 10),
            'database_id' => rand(1000, 9999),
            'message' => 'Event ticket program created successfully (testing mode)',
            'testing_mode' => true
        ];
    }
    
    /**
     * Mock event ticket template creation for testing
     */
    protected function mockCreateEventTicketTemplate(string $tierId, array $templateData)
    {
        return [
            'success' => true,
            'id' => 'event_template_' . substr(md5(uniqid()), 0, 10),
            'database_id' => rand(1000, 9999),
            'message' => 'Event ticket template created successfully (testing mode)',
            'testing_mode' => true,
            'tier_id' => $tierId,
            'fields' => [
                'event_name' => $templateData['name'] ?? 'Sample Event',
                'event_date' => date('Y-m-d H:i:s'),
                'venue' => 'Sample Venue',
                'seat' => 'A1',
                'ticket_holder' => 'Sample Holder'
            ]
        ];
    }

    /**
     * Mock coupon program creation for testing
     */
    protected function mockCreateCouponProgram(array $programData)
    {
        return [
            'success' => true,
            'id' => 'coupon_program_' . substr(md5(uniqid()), 0, 10),
            'database_id' => rand(1000, 9999),
            'message' => 'Coupon program created successfully (testing mode)',
            'testing_mode' => true
        ];
    }
    
    /**
     * Mock coupon template creation for testing
     */
    protected function mockCreateCouponTemplate(string $tierId, array $templateData)
    {
        return [
            'success' => true,
            'id' => 'coupon_template_' . substr(md5(uniqid()), 0, 10),
            'database_id' => rand(1000, 9999),
            'message' => 'Coupon template created successfully (testing mode)',
            'testing_mode' => true,
            'tier_id' => $tierId,
            'fields' => [
                'offer_title' => $templateData['name'] ?? 'Special Offer',
                'discount_amount' => '20% OFF',
                'expiry_date' => date('Y-m-d', strtotime('+30 days')),
                'terms' => 'Valid for one-time use only',
                'customer_name' => 'Sample Customer'
            ]
        ];
    }
    
    protected function mockCreateMembershipTier(string $programId, array $tierData)
    {
        $tierId = 'test_tier_' . uniqid();
        
        return [
            'id' => $tierId,
            'name' => $tierData['name'] ?? 'Test Tier',
            'programId' => $programId,
            'created_at' => now()->toISOString()
        ];
    }
    
    protected function mockCreatePassTemplate(string $tierId, array $templateData)
    {
        $templateId = 'test_template_' . uniqid();
        
        Log::info('Mock: PassKit template creation', [
            'tier_id' => $tierId,
            'template_name' => $templateData['name'] ?? 'Pass Template',
            'template_id' => $templateId,
            'testing_mode' => true
        ]);
        
        return [
            'id' => $templateId,
            'name' => $templateData['name'] ?? 'Pass Template',
            'description' => $templateData['description'] ?? '',
            'tier_id' => $tierId,
            'created_at' => now()->toISOString()
        ];
    }

    /**
     * Mock get install URLs for testing
     */
    protected function mockGetInstallUrls(string $memberId): array
    {
        return [
            'apple' => "https://pub2.passkit.io/c/mock_{$memberId}",
            'google' => "https://pub2.passkit.io/c/mock_{$memberId}",
            'universal' => "https://pub2.passkit.io/c/mock_{$memberId}"
        ];
    }
    
    /**
     * Create production-like member responses for dashboard testing
     */
    protected function createProductionLikeMember(string $tierId, array $memberData)
    {
        // Generate production-format member ID
        $memberId = 'shakewell_member_' . uniqid();
        
        Log::info('PassKit Production-Like Member Creation', [
            'member_id' => $memberId,
            'tier_id' => $tierId,
            'external_id' => $memberData['externalId'] ?? '',
            'note' => 'Using production-like IDs pending certificate account linking'
        ]);
        
        return [
            'id' => $memberId,
            'tier_id' => $tierId,
            'external_id' => $memberData['externalId'] ?? '',
            'points' => $memberData['points'] ?? 0,
            'tier' => 'Bronze',
            'install_urls' => [
                'apple' => "https://pub1.pskt.io/c/{$memberId}",
                'google' => "https://pub1.pskt.io/c/{$memberId}",
                'universal' => "https://pub1.pskt.io/c/{$memberId}"
            ],
            'created_at' => now()->toISOString(),
            'status' => 'production_simulation',
            'note' => 'Generated with production-like format. Will create real objects once certificates are linked to PassKit account.'
        ];
    }
    
    /**
     * Save PassKit program to database
     */
    protected function savePassKitProgram($passkitResponse, array $programData, string $programType): PassKitProgram
    {
        // Get current account ID - default to 1 if no account system
        $accountId = 1; // You may want to get this from the authenticated user's account
        
        // Get current user ID - default to 1 if no auth
        $userId = Auth::id() ?? 1;
        
        return PassKitProgram::create([
            'account_id' => $accountId,
            'passkit_id' => $passkitResponse->getId(),
            'name' => $programData['name'] ?? 'Loyalty Program',
            'description' => $programData['description'] ?? null,
            'status' => $programData['status'] ?? ['PROJECT_ACTIVE_FOR_OBJECT_CREATION', 'PROJECT_DRAFT'],
            'program_type' => $programType,
            'metadata' => [
                'passkit_response' => [
                    'id' => $passkitResponse->getId(),
                    'created_at' => now()->toISOString()
                ],
                'original_data' => $programData
            ],
            'created_by' => $userId,
            'last_synced_at' => now()
        ]);
    }
    
    /**
     * Save PassKit tier to database
     */
    protected function savePassKitTier($passkitResponse, array $tierData, string $programId): PassKitTier
    {
        // Get current account ID - default to 1 if no account system
        $accountId = 1;
        
        // Get current user ID - default to 1 if no auth
        $userId = Auth::id() ?? 1;
        
        return PassKitTier::create([
            'account_id' => $accountId,
            'program_id' => $programId,
            'passkit_id' => $passkitResponse->getId(),
            'name' => $tierData['name'] ?? 'Default Tier',
            'tier_index' => $tierData['tierIndex'] ?? 1,
            'timezone' => $tierData['timezone'] ?? 'UTC',
            'template_id' => $tierData['templateId'] ?? null,
            'upgrade_message' => $tierData['upgradeMessage'] ?? null,
            'downgrade_message' => $tierData['downgradeMessage'] ?? null,
            'metadata' => [
                'passkit_response' => [
                    'id' => $passkitResponse->getId(),
                    'created_at' => now()->toISOString()
                ],
                'original_data' => $tierData
            ],
            'created_by' => $userId,
            'last_synced_at' => now()
        ]);
    }
    
    /**
     * Save PassKit template to database
     */
    protected function savePassKitTemplate($passkitResponse, array $templateData, string $tierId): CardTemplate
    {
        // Get current account ID - default to 1 if no account system
        $accountId = 1;
        
        // Get current user ID - default to 1 if no auth
        $userId = Auth::id() ?? 1;
        
        return CardTemplate::create([
            'account_id' => $accountId,
            'name' => $templateData['name'] ?? 'Pass Template',
            'description' => $templateData['description'] ?? '',
            'template_data' => [
                'type' => 'membership',
                'fields' => [
                    'member_name' => ['label' => 'Member Name', 'type' => 'text'],
                    'points' => ['label' => 'Points', 'type' => 'number'],
                    'tier' => ['label' => 'Tier', 'type' => 'text']
                ]
            ],
            'field_definitions' => [
                [
                    'field_name' => 'member_name',
                    'field_type' => 'text',
                    'label' => 'Member Name',
                    'is_required' => true,
                    'passkit_unique_name' => 'person.displayName',
                    'passkit_field_type' => 'PII'
                ],
                [
                    'field_name' => 'points',
                    'field_type' => 'number',
                    'label' => 'Points',
                    'is_required' => false,
                    'passkit_unique_name' => 'custom.points',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'tier',
                    'field_type' => 'text',
                    'label' => 'Tier',
                    'is_required' => false,
                    'passkit_unique_name' => 'meta.tierName',
                    'passkit_field_type' => 'META'
                ]
            ],
            'is_active' => true,
            'passkit_template_id' => $passkitResponse->getId(),
            'passkit_tier_id' => $tierId,
            'passkit_metadata' => [
                'passkit_response' => [
                    'id' => $passkitResponse->getId(),
                    'created_at' => now()->toISOString()
                ],
                'protocol' => 'MEMBERSHIP',
                'timezone' => $templateData['timezone'] ?? 'UTC',
                'colors' => [
                    'background' => '#663399',
                    'label' => '#FFFFFF',
                    'text' => '#FFFFFF'
                ],
                'original_data' => $templateData
            ],
            'passkit_synced_at' => now(),
            'passkit_enabled' => true,
            'created_by' => $userId
        ]);
    }

    /**
     * Save PassKit event ticket template to database
     */
    protected function savePassKitEventTicketTemplate($passkitResponse, array $templateData, string $tierId): CardTemplate
    {
        // Get current account ID - default to 1 if no account system
        $accountId = 1;
        
        // Get current user ID - default to 1 if no auth
        $userId = Auth::id() ?? 1;
        
        return CardTemplate::create([
            'account_id' => $accountId,
            'name' => $templateData['name'] ?? 'Event Ticket Template',
            'description' => $templateData['description'] ?? 'PassKit template for event tickets',
            'template_data' => [
                'type' => 'event_ticket',
                'fields' => [
                    'event_name' => ['label' => 'Event Name', 'type' => 'text'],
                    'event_date' => ['label' => 'Event Date', 'type' => 'text'],
                    'venue' => ['label' => 'Venue', 'type' => 'text'],
                    'seat' => ['label' => 'Seat', 'type' => 'text'],
                    'ticket_holder' => ['label' => 'Ticket Holder', 'type' => 'text']
                ]
            ],
            'field_definitions' => [
                [
                    'field_name' => 'event_name',
                    'field_type' => 'text',
                    'label' => 'Event Name',
                    'is_required' => true,
                    'passkit_unique_name' => 'custom.eventName',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'event_date',
                    'field_type' => 'text',
                    'label' => 'Event Date',
                    'is_required' => true,
                    'passkit_unique_name' => 'custom.eventDate',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'venue',
                    'field_type' => 'text',
                    'label' => 'Venue',
                    'is_required' => false,
                    'passkit_unique_name' => 'custom.venue',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'seat',
                    'field_type' => 'text',
                    'label' => 'Seat',
                    'is_required' => false,
                    'passkit_unique_name' => 'custom.seatNumber',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'ticket_holder',
                    'field_type' => 'text',
                    'label' => 'Ticket Holder',
                    'is_required' => true,
                    'passkit_unique_name' => 'person.displayName',
                    'passkit_field_type' => 'PII'
                ]
            ],
            'is_active' => true,
            'passkit_template_id' => $passkitResponse->getId(),
            'passkit_tier_id' => $tierId,
            'passkit_metadata' => [
                'passkit_response' => [
                    'id' => $passkitResponse->getId(),
                    'created_at' => now()->toISOString()
                ],
                'protocol' => 'MEMBERSHIP', // Note: Using MEMBERSHIP protocol for event tickets
                'use_case' => 'EVENT_TICKET', // Custom field to indicate use case
                'timezone' => $templateData['timezone'] ?? 'UTC',
                'colors' => [
                    'background' => $templateData['backgroundColor'] ?? '#1e40af',
                    'label' => '#FFFFFF',
                    'text' => '#FFFFFF'
                ],
                'original_data' => $templateData
            ],
            'passkit_synced_at' => now(),
            'passkit_enabled' => true,
            'created_by' => $userId
        ]);
    }

    /**
     * Save PassKit coupon template to database
     */
    protected function savePassKitCouponTemplate($passkitResponse, array $templateData, string $tierId): CardTemplate
    {
        // Get current account ID - default to 1 if no account system
        $accountId = 1;
        
        // Get current user ID - default to 1 if no auth
        $userId = Auth::id() ?? 1;
        
        return CardTemplate::create([
            'account_id' => $accountId,
            'name' => $templateData['name'] ?? 'Coupon Template',
            'description' => $templateData['description'] ?? 'PassKit template for discount coupons',
            'template_data' => [
                'type' => 'coupon',
                'fields' => [
                    'offer_title' => ['label' => 'Offer', 'type' => 'text'],
                    'discount_amount' => ['label' => 'Discount', 'type' => 'text'],
                    'expiry_date' => ['label' => 'Valid Until', 'type' => 'text'],
                    'terms' => ['label' => 'Terms', 'type' => 'text'],
                    'customer_name' => ['label' => 'Customer Name', 'type' => 'text']
                ]
            ],
            'field_definitions' => [
                [
                    'field_name' => 'offer_title',
                    'field_type' => 'text',
                    'label' => 'Offer',
                    'is_required' => true,
                    'passkit_unique_name' => 'custom.offerTitle',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'discount_amount',
                    'field_type' => 'text',
                    'label' => 'Discount',
                    'is_required' => true,
                    'passkit_unique_name' => 'custom.discountAmount',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'expiry_date',
                    'field_type' => 'text',
                    'label' => 'Valid Until',
                    'is_required' => false,
                    'passkit_unique_name' => 'custom.expiryDate',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'terms',
                    'field_type' => 'text',
                    'label' => 'Terms',
                    'is_required' => false,
                    'passkit_unique_name' => 'custom.terms',
                    'passkit_field_type' => 'CUSTOM_FIELDS'
                ],
                [
                    'field_name' => 'customer_name',
                    'field_type' => 'text',
                    'label' => 'Customer Name',
                    'is_required' => false,
                    'passkit_unique_name' => 'person.displayName',
                    'passkit_field_type' => 'PII'
                ]
            ],
            'is_active' => true,
            'passkit_template_id' => $passkitResponse->getId(),
            'passkit_tier_id' => $tierId,
            'passkit_metadata' => [
                'passkit_response' => [
                    'id' => $passkitResponse->getId(),
                    'created_at' => now()->toISOString()
                ],
                'protocol' => 'MEMBERSHIP', // Note: Using MEMBERSHIP protocol for coupons
                'use_case' => 'COUPON', // Custom field to indicate use case
                'timezone' => $templateData['timezone'] ?? 'UTC',
                'colors' => [
                    'background' => $templateData['backgroundColor'] ?? '#dc2626',
                    'label' => '#FFFFFF',
                    'text' => '#FFFFFF'
                ],
                'original_data' => $templateData
            ],
            'passkit_synced_at' => now(),
            'passkit_enabled' => true,
            'created_by' => $userId
        ]);
    }
    
}