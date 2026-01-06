<?php

namespace App\State;

use App\Entity\Race;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RaceUserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security
    ) {}

    public function process(mixed $data, ?Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Si on crée une nouvelle course
        if ($data instanceof Race && $this->security->getUser()) {
            // On force l'utilisateur connecté comme propriétaire
            $data->setUser($this->security->getUser());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
