<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Mail\ImapEmailReaderAdapter;
use App\Services\Integration\IntegrationCredentialService;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;
use Illuminate\Support\Facades\Crypt;
use Mockery;

class ImapEmailReaderAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected IntegrationCredentialService $credentialService;
    protected Integration $integration;

    protected \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->first();
        $this->user = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($this->user);

        $this->credentialService = app(IntegrationCredentialService::class);

        $this->integration = Integration::create([
            'organization_id' => $this->user->organization_id,
            'name' => 'Security Mailbox',
            'code' => 'email',
            'type' => 'email',
            'host' => 'imap.example.com',
            'username' => 'security@example.com',
            'status' => 'Connected',
            'created_by' => $this->user->id,
        ]);
        
        $this->credentialService->store($this->integration->id, 'password', 'secure-imap-password');
    }

    public function test_it_throws_if_no_integration_found()
    {
        $adapter = new ImapEmailReaderAdapter($this->credentialService);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No connected email integration found');
        
        $adapter->readAndNormalize('nonexistent@example.com');
    }

    public function test_it_throws_if_no_password_credential_found()
    {
        // Create an integration but no password
        Integration::create([
            'organization_id' => $this->user->organization_id,
            'name' => 'No Password',
            'code' => 'email',
            'type' => 'email',
            'username' => 'nopass@example.com',
            'status' => 'Connected',
            'created_by' => $this->user->id,
        ]);
        
        $adapter = new ImapEmailReaderAdapter($this->credentialService);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No valid password credential found');
        
        $adapter->readAndNormalize('nopass@example.com');
    }

    public function test_it_reads_and_normalizes_security_signals_successfully()
    {
        // Mock IMAP Client Manager & Client
        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClient = Mockery::mock(Client::class);
        $mockFolder = Mockery::mock(Folder::class);
        $mockQuery = Mockery::mock(WhereQuery::class);
        
        // Setup mock message 1 (Credential exposure)
        $msg1 = Mockery::mock(Message::class);
        $msg1->shouldReceive('getSubject')->andReturn('GitHub API Token');
        $msg1->shouldReceive('getTextBody')->andReturn('Your new token: ghp_12345secret');
        $msg1->shouldReceive('getHTMLBody')->andReturnNull();
        $msg1->shouldReceive('getFrom')->andReturn([ (object)['mail' => 'admin@github.com'] ]);
        $msg1->shouldReceive('getDate')->andReturn(now());
        $msg1->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection());
        $msg1->shouldReceive('getMessageId')->andReturn('msg-123');
        
        // Setup mock message 2 (No security signal)
        $msg2 = Mockery::mock(Message::class);
        $msg2->shouldReceive('getSubject')->andReturn('Weekly Newsletter');
        $msg2->shouldReceive('getTextBody')->andReturn('Check out our latest features.');
        $msg2->shouldReceive('getHTMLBody')->andReturnNull();
        $msg2->shouldReceive('getFrom')->andReturn([ (object)['mail' => 'marketing@example.com'] ]);
        $msg2->shouldReceive('getDate')->andReturn(now());
        $msg2->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection());
        
        // Setup mock message 3 (Phishing / Suspicious Link)
        $msg3 = Mockery::mock(Message::class);
        $msg3->shouldReceive('getSubject')->andReturn('URGENT: Verify your account');
        $msg3->shouldReceive('getTextBody')->andReturn('Please click here to verify your account: http://evil.com/login');
        $msg3->shouldReceive('getHTMLBody')->andReturnNull();
        $msg3->shouldReceive('getFrom')->andReturn([ (object)['mail' => 'support-verification@scammer.com'] ]);
        $msg3->shouldReceive('getDate')->andReturn(now());
        $msg3->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection());
        $msg3->shouldReceive('getMessageId')->andReturn('msg-789');

        $messages = new MessageCollection([$msg1, $msg2, $msg3]);
        
        $mockClientManager->shouldReceive('make')
            ->once()
            ->with(Mockery::on(function ($config) {
                return $config['username'] === 'security@example.com' &&
                       $config['password'] === 'secure-imap-password';
            }))
            ->andReturn($mockClient);
            
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);
        $mockClient->shouldReceive('disconnect')->once();
        
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);
        $mockQuery->shouldReceive('unseen')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->with(50)->once()->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messages);

        // Run the adapter
        $adapter = new ImapEmailReaderAdapter($this->credentialService);
        $adapter->clientManager = $mockClientManager; // Inject mock
        
        $signals = $adapter->readAndNormalize('security@example.com', 50, true);
        
        // Assertions
        $this->assertCount(2, $signals, 'Should only return 2 messages that contain security signals');
        
        $this->assertEquals('msg-123', $signals[0]['id']);
        $this->assertEquals('credential_exposure', $signals[0]['security_signals'][0]);
        
        $this->assertEquals('msg-789', $signals[1]['id']);
        $this->assertEquals('suspicious_phishing', $signals[1]['security_signals'][0]);
    }
}
