<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Action\Api;

use Danilovl\LogViewerBundle\Action\Api\GetEntriesNewAction;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogViewer,
    LogSourceManager
};
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use PHPUnit\Framework\MockObject\{
    MockObject,
    Stub
};
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
    /** @var MockObject&LogViewer $logViewer */
    private LogViewer $logViewer;

    /** @var Stub&LogSourceManager $logSourceManager */
    private LogSourceManager $logSourceManager;

    /** @var Stub&RequestStack $requestStack */
    private RequestStack $requestStack;

    /** @var Stub&TokenStorageInterface $tokenStorage */
    private TokenStorageInterface $tokenStorage;

    private GetEntriesNewAction $action;

    protected function setUp(): void
    {
        /** @var MockObject&LogViewer $logViewer */
        $logViewer = $this->createMock(LogViewer::class);
        $this->logViewer = $logViewer;

        /** @var Stub&LogSourceManager $logSourceManager */
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
            aiChats: [],
            apiPrefix: '',
            encoreBuildName: null,
            sourceRemoteHosts: [],
            notifierEnabled: false,
            notifierRules: []
        );
        /** @var Stub&RequestStack $requestStack */
        $requestStack = $this->createStub(RequestStack::class);
        $this->requestStack = $requestStack;

        /** @var Stub&TokenStorageInterface $tokenStorage */
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
        /** @var Stub&UserInterface $user */
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test-user');

        /** @var Stub&TokenInterface $token */
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        /** @var Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn($token);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var MockObject&LogViewer $logViewer */
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
        /** @var Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn(null);

        /** @var Stub&SessionInterface $session */
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session-id-123');

        /** @var Stub&RequestStack $requestStack */
        $requestStack = $this->requestStack;
        $requestStack->method('getSession')->willReturn($session);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var MockObject&LogViewer $logViewer */
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
        /** @var Stub&TokenInterface $token */
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        /** @var Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn($token);

        /** @var Stub&SessionInterface $session */
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session-id-anon');

        /** @var Stub&RequestStack $requestStack */
        $requestStack = $this->requestStack;
        $requestStack->method('getSession')->willReturn($session);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var MockObject&LogViewer $logViewer */
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
        /** @var Stub&UserInterface $user */
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('');

        /** @var Stub&TokenInterface $token */
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        /** @var Stub&TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->tokenStorage;
        $tokenStorage->method('getToken')->willReturn($token);

        /** @var Stub&SessionInterface $session */
        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session-id-empty');

        /** @var Stub&RequestStack $requestStack */
        $requestStack = $this->requestStack;
        $requestStack->method('getSession')->willReturn($session);

        $this->logSourceManager->method('getAllSources')->willReturn([]);

        /** @var MockObject&LogViewer $logViewer */
        $logViewer = $this->logViewer;
        $logViewer->expects($this->once())
            ->method('loadWatcherPositions')
            ->with('session-id-empty')
            ->willReturn([]);

        $response = $this->action->__invoke();
        $this->assertEquals(200, $response->getStatusCode());
    }
}
