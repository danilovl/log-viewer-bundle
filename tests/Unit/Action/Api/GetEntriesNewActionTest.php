<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Action\Api;

use Danilovl\LogViewerBundle\Action\Api\GetEntriesNewAction;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogViewer,
    LogSourceManager
};
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{
    RequestStack,
    Session\SessionInterface
};
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class GetEntriesNewActionTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&LogViewer $logViewer */
    private LogViewer $logViewer;

    /** @var \PHPUnit\Framework\MockObject\Stub&LogSourceManager $logSourceManager */
    private LogSourceManager $logSourceManager;

    /** @var \PHPUnit\Framework\MockObject\Stub&RequestStack $requestStack */
    private RequestStack $requestStack;

    /** @var \PHPUnit\Framework\MockObject\Stub&TokenStorageInterface $tokenStorage */
    private TokenStorageInterface $tokenStorage;

    private GetEntriesNewAction $action;

    protected function setUp(): void
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject&LogViewer $logViewer */
        $logViewer = $this->createMock(LogViewer::class);
        $this->logViewer = $logViewer;

        /** @var \PHPUnit\Framework\MockObject\Stub&LogSourceManager $logSourceManager */
        $logSourceManager = $this->createStub(LogSourceManager::class);
        $this->logSourceManager = $logSourceManager;
        $configurationProvider = new ConfigurationProvider(
            sourceDirs: [],
            sourceFiles: [],
            sourceIgnore: [],
            sourceMaxFileSize: null,
            sourceAllowDelete: false,
            sourceAllowDownload: false,
            parserDefault: null,
            parserOverrides: [],
            parserGoEnabled: false,
            parserGoBinaryPath: '',
            cacheParserDetectEnabled: false,
            cacheStatisticEnabled: false,
            cacheStatisticInterval: 0,
            dashboardPageStatisticEnabled: false,
            dashboardPageAutoRefreshEnabled: false,
            dashboardPageAutoRefreshInterval: 0,
            dashboardPageAutoRefreshShowCountdown: false,
            liveLogPageEnabled: false,
            liveLogPageInterval: 0,
            logPageStatisticEnabled: false,
            logPageAutoRefreshEnabled: false,
            logPageAutoRefreshInterval: 0,
            logPageAutoRefreshShowCountdown: false,
            logPageLimit: 50,
            aiButtonLevels: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: [],
            notifierEnabled: false,
            notifierRules: []
        );
        /** @var \PHPUnit\Framework\MockObject\Stub&RequestStack $requestStack */
        $requestStack = $this->createStub(RequestStack::class);
        $this->requestStack = $requestStack;

        /** @var \PHPUnit\Framework\MockObject\Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $this->tokenStorage = $tokenStorage;

        $this->action = new GetEntriesNewAction(
            logViewer: $this->logViewer,
            logSourceManager: $this->logSourceManager,
            requestStack: $this->requestStack,
            tokenStorage: $this->tokenStorage
        );
    }

    public function testInvokeWithUserIdentifier(): void
    {
        /** @var \PHPUnit\Framework\MockObject\Stub&UserInterface $user */
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test-user');

        /** @var \PHPUnit\Framework\MockObject\Stub&TokenInterface $token */
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        /** @var \PHPUnit\Framework\MockObject\Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn($token);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var \PHPUnit\Framework\MockObject\MockObject&LogViewer $logViewer */
        $logViewer = $this->logViewer;
        $logViewer
            ->expects($this->once())
            ->method('loadWatcherPositions')
            ->with('test-user')
            ->willReturn([]);

        $response = $this->action->__invoke();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvokeWithSessionIdWhenNoToken(): void
    {
        /** @var \PHPUnit\Framework\MockObject\Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn(null);

        /** @var \PHPUnit\Framework\MockObject\Stub&SessionInterface $session */
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session-id-123');

        /** @var \PHPUnit\Framework\MockObject\Stub&RequestStack $requestStack */
        $requestStack = $this->requestStack;
        $requestStack->method('getSession')->willReturn($session);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var \PHPUnit\Framework\MockObject\MockObject&LogViewer $logViewer */
        $logViewer = $this->logViewer;
        $logViewer->expects($this->once())
            ->method('loadWatcherPositions')
            ->with('session-id-123')
            ->willReturn([]);

        $response = $this->action->__invoke();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvokeWithSessionIdWhenAnonymousToken(): void
    {
        /** @var \PHPUnit\Framework\MockObject\Stub&TokenInterface $token */
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        /** @var \PHPUnit\Framework\MockObject\Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn($token);

        /** @var \PHPUnit\Framework\MockObject\Stub&SessionInterface $session */
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session-id-anon');

        /** @var \PHPUnit\Framework\MockObject\Stub&RequestStack $requestStack */
        $requestStack = $this->requestStack;
        $requestStack->method('getSession')->willReturn($session);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var \PHPUnit\Framework\MockObject\MockObject&LogViewer $logViewer */
        $logViewer = $this->logViewer;
        $logViewer->expects($this->once())
            ->method('loadWatcherPositions')
            ->with('session-id-anon')
            ->willReturn([]);

        $response = $this->action->__invoke();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvokeWithSessionIdWhenEmptyUserIdentifier(): void
    {
        /** @var \PHPUnit\Framework\MockObject\Stub&UserInterface $user */
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('');

        /** @var \PHPUnit\Framework\MockObject\Stub&TokenInterface $token */
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        /** @var \PHPUnit\Framework\MockObject\Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn($token);

        /** @var \PHPUnit\Framework\MockObject\Stub&SessionInterface $session */
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session-id-empty');

        /** @var \PHPUnit\Framework\MockObject\Stub&RequestStack $requestStack */
        $requestStack = $this->requestStack;
        $requestStack->method('getSession')->willReturn($session);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var \PHPUnit\Framework\MockObject\MockObject&LogViewer $logViewer */
        $logViewer = $this->logViewer;
        $logViewer->expects($this->once())
            ->method('loadWatcherPositions')
            ->with('session-id-empty')
            ->willReturn([]);

        $response = $this->action->__invoke();
        $this->assertEquals(200, $response->getStatusCode());
    }
}
