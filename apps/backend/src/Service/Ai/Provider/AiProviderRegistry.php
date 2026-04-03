<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use App\Enum\AiAgentType;

class AiProviderRegistry
{
    /** @var AiProviderInterface[] */
    private array $providers;

    /**
     * @param iterable<AiProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers instanceof \Traversable
            ? iterator_to_array($providers)
            : (array) $providers;
    }

    public function getProvider(AiAgentType $agentType): AiProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($agentType)) {
                return $provider;
            }
        }

        throw new \RuntimeException(sprintf(
            'No AI provider registered for agent type "%s"',
            $agentType->value,
        ));
    }
}
