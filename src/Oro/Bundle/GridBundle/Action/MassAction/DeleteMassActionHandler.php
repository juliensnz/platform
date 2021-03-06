<?php

namespace Oro\Bundle\GridBundle\Action\MassAction;

use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\GridBundle\Action\MassAction\MassActionMediatorInterface;
use Oro\Bundle\GridBundle\Action\MassAction\MassActionResponse;

class DeleteMassActionHandler implements MassActionHandlerInterface
{
    const FLUSH_BATCH_SIZE = 20;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var string
     */
    protected $responseMessage = 'oro.grid.mass_action.delete.success_message';

    /**
     * @param EntityManager $entityManager
     * @param TranslatorInterface $translator
     */
    public function __construct(EntityManager $entityManager, TranslatorInterface $translator)
    {
        $this->entityManager = $entityManager;
        $this->translator    = $translator;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(MassActionMediatorInterface $mediator)
    {
        $iteration = 0;
        $entityName = null;
        $entityIdentifiedField = null;

        $results = $mediator->getResults();

        // batch remove should be processed in transaction
        $this->entityManager->beginTransaction();
        try {
            foreach ($results as $result) {
                $entity = $result->getRootEntity();
                if (!$entity) {
                    // no entity in result record, it should be extracted from DB
                    if (!$entityName) {
                        $entityName = $this->getEntityName($mediator);
                    }
                    if (!$entityIdentifiedField) {
                        $entityIdentifiedField = $this->getEntityIdentifierField($mediator);
                    }
                    $entity = $this->getEntity($entityName, $result->getValue($entityIdentifiedField));
                }

                if ($entity) {
                    $this->entityManager->remove($entity);

                    $iteration++;
                    if ($iteration % self::FLUSH_BATCH_SIZE == 0) {
                        $this->entityManager->flush();
                    }
                }
            }

            if ($iteration % self::FLUSH_BATCH_SIZE > 0) {
                $this->entityManager->flush();
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $this->getResponse($mediator, $iteration);
    }

    /**
     * @param MassActionMediatorInterface $mediator
     * @param int $entitiesCount
     * @return MassActionResponse
     */
    protected function getResponse(MassActionMediatorInterface $mediator, $entitiesCount = 0)
    {
        $massAction = $mediator->getMassAction();
        $messages = $massAction->getOption('messages');
        $responseMessage = !empty($messages) && !empty($messages['success'])
                ? $messages['success']
                : $this->responseMessage;

        $successful = $entitiesCount > 0;
        $options = array('count' => $entitiesCount);

        return new MassActionResponse(
            $successful,
            $this->translator->transChoice(
                $responseMessage,
                $entitiesCount,
                array('%count%' => $entitiesCount)
            ),
            $options
        );
    }

    /**
     * @param MassActionMediatorInterface $mediator
     * @return string
     * @throws \LogicException
     */
    protected function getEntityName(MassActionMediatorInterface $mediator)
    {
        $entityName = $mediator->getDatagrid()->getEntityName();
        if (!$entityName) {
            $massAction = $mediator->getMassAction();
            $entityName = $massAction->getOption('entity_name');
            if (!$entityName) {
                throw new \LogicException(sprintf('Mass action "%s" must define entity name', $massAction->getName()));
            }
        }

        return $entityName;
    }

    /**
     * @param MassActionMediatorInterface $mediator
     * @return string
     */
    protected function getEntityIdentifierField(MassActionMediatorInterface $mediator)
    {
        return $mediator->getDatagrid()->getIdentifierField()->getFieldName();
    }

    /**
     * @param string $entityName
     * @param mixed $identifierValue
     * @return object
     */
    protected function getEntity($entityName, $identifierValue)
    {
        return $this->entityManager->getReference($entityName, $identifierValue);
    }
}
