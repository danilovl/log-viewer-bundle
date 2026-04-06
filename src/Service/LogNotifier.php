<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Service;

use Danilovl\LogViewerBundle\DTO\{
    LogEntry,
    NotifierRule
};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\NoRecipient;

final readonly class LogNotifier
{
    public function __construct(
        private ConfigurationProvider $configurationProvider,
        #[Autowire(service: 'notifier')]
        private ?NotifierInterface $notifier = null
    ) {}

    /**
     * @param LogEntry[] $entries
     */
    public function notify(string $sourceName, array $entries): void
    {
        if (!$this->configurationProvider->notifierEnabled || $this->notifier === null) {
            return;
        }
        
        $rules = $this->configurationProvider->getNotifierRules();

        if (empty($rules)) {
            return;
        }

        foreach ($entries as $entry) {
            foreach ($rules as $rule) {
                if ($this->matchRule($rule, $entry)) {
                    $this->sendNotification($sourceName, $entry, $rule);
                }
            }
        }
    }

    private function matchRule(NotifierRule $rule, LogEntry $entry): bool
    {
        if (!empty($rule->levels) && !in_array(mb_strtolower($entry->level), array_map('strtolower', $rule->levels), true)) {
            return false;
        }

        if (!empty($rule->contains)) {
            foreach ($rule->contains as $keyword) {
                if (str_contains($entry->message, $keyword)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function sendNotification(string $sourceName, LogEntry $entry, NotifierRule $rule): void
    {
        if ($this->notifier === null) {
            return;
        }

        $subject = sprintf('[%s] %s log entry detected', $rule->name, $entry->level);
        $content = sprintf(
            "Source: %s\nLevel: %s\nDate: %s\nMessage: %s",
            $sourceName,
            $entry->level,
            $entry->timestamp,
            $entry->message
        );

        foreach ($rule->channels as $channel) {
            $notification = new Notification($subject, [$channel]);
            $notification->content($content);
            $notification->importance($this->getImportance($entry->level));

            $this->notifier->send($notification, new NoRecipient);
        }
    }

    private function getImportance(string $level): string
    {
        return match (mb_strtolower($level)) {
            'emergency', 'alert', 'critical' => Notification::IMPORTANCE_URGENT,
            'error' => Notification::IMPORTANCE_HIGH,
            'warning' => Notification::IMPORTANCE_MEDIUM,
            default => Notification::IMPORTANCE_LOW,
        };
    }
}
