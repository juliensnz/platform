<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Exception\UnknownAttributeException;
use Oro\Bundle\WorkflowBundle\Model\WorkflowRegistry;
use Oro\Bundle\WorkflowBundle\Model\Attribute;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowItemRepository;
use Oro\Bundle\WorkflowBundle\Model\DoctrineHelper;
use Oro\Bundle\WorkflowBundle\Exception\WorkflowException;

class WorkflowManager
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var WorkflowRegistry
     */
    protected $workflowRegistry;

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @param ManagerRegistry $registry
     * @param WorkflowRegistry $workflowRegistry
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(
        ManagerRegistry $registry,
        WorkflowRegistry $workflowRegistry,
        DoctrineHelper $doctrineHelper
    ) {
        $this->registry = $registry;
        $this->workflowRegistry = $workflowRegistry;
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * @param string|Workflow $workflow
     * @param object|null $entity
     * @return Collection
     */
    public function getAllowedStartTransitions($workflow, $entity = null)
    {
        $workflow = $this->getWorkflow($workflow);
        $initData = $this->getWorkflowData($workflow, $entity);

        return $workflow->getAllowedStartTransitions($initData);
    }

    /**
     * @param WorkflowItem $workflowItem
     * @return Collection
     */
    public function getAllowedTransitions(WorkflowItem $workflowItem)
    {
        $workflow = $this->getWorkflow($workflowItem);

        return $workflow->getAllowedTransitions($workflowItem);
    }

    /**
     * @param string $workflow
     * @param object|null $entity
     * @param string|Transition|null $transition
     * @param array $data
     * @return WorkflowItem
     * @throws \Exception
     */
    public function startWorkflow($workflow, $entity = null, $transition = null, array $data = array())
    {
        $workflow = $this->getWorkflow($workflow);
        $initData = $this->getWorkflowData($workflow, $entity, $data);

        /** @var EntityManager $em */
        $em = $this->registry->getManager();
        $em->beginTransaction();
        try {
            $workflowItem = $workflow->start($initData, $transition);
            $em->persist($workflowItem);
            $em->flush();
            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }

        return $workflowItem;
    }

    /**
     * Perform workflow item transition.
     *
     * @param WorkflowItem $workflowItem
     * @param string|Transition $transition
     * @throws \Exception
     */
    public function transit(WorkflowItem $workflowItem, $transition)
    {
        $workflow = $this->getWorkflow($workflowItem);
        /** @var EntityManager $em */
        $em = $this->registry->getManager();
        $em->beginTransaction();
        try {
            $workflow->transit($workflowItem, $transition);
            $workflowItem->setUpdated();
            $em->flush();
            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }
    }

    /**
     * @param object $entity
     * @param WorkflowItem[]|Collection $workflowItems
     * @param string|null $workflowName
     * @return Workflow[]
     */
    public function getApplicableWorkflows($entity, $workflowItems = null, $workflowName = null)
    {
        if (null === $workflowItems) {
            $workflowItems = $this->getWorkflowItemsByEntity($entity, $workflowName);
        }

        $usedWorkflows = array();
        foreach ($workflowItems as $workflowItem) {
            $usedWorkflows[] = $workflowItem->getWorkflowName();
        }

        $entityClass = $this->doctrineHelper->getEntityClass($entity);
        if ($workflowName) {
            try {
                $allowedWorkflows = array($this->workflowRegistry->getWorkflow($workflowName));
            } catch (WorkflowNotFoundException $e) {
                $allowedWorkflows = array();
            }
        } else {
            $allowedWorkflows = $this->workflowRegistry->getWorkflowsByEntityClass($entityClass);
        }

        $applicableWorkflows = array();
        foreach ($allowedWorkflows as $workflow) {
            if ($workflow->isEnabled()) {
                $managedEntityAttribute = $this->getManagedEntityAttributeByEntity($workflow, $entity);
                if ($managedEntityAttribute) {
                    $isMultiple = $managedEntityAttribute->getOption('multiple') == true;

                    // if workflow allows multiple workflow items or there is no workflow item for current class
                    if ($isMultiple || !in_array($workflow->getName(), $usedWorkflows)) {
                        $applicableWorkflows[$workflow->getName()] = $workflow;
                    }
                }
            }
        }

        return $applicableWorkflows;
    }

    /**
     * Get workflow items for entity.
     *
     * @param object $entity
     * @param string|null $workflowName
     * @param string|null $workflowType
     * @return WorkflowItem[]
     */
    public function getWorkflowItemsByEntity($entity, $workflowName = null, $workflowType = null)
    {
        $entityClass = $this->doctrineHelper->getEntityClass($entity);
        $entityIdentifier = $this->doctrineHelper->getEntityIdentifier($entity);

        /** @var WorkflowItemRepository $workflowItemsRepository */
        $workflowItemsRepository = $this->registry->getRepository('OroWorkflowBundle:WorkflowItem');

        return $workflowItemsRepository->findByEntityMetadata(
            $entityClass,
            $entityIdentifier,
            $workflowName,
            $workflowType
        );
    }

    /**
     * @param Workflow $workflow
     * @param object $entity
     * @param array $data
     * @return array
     * @throws UnknownAttributeException
     */
    protected function getWorkflowData(Workflow $workflow, $entity = null, array $data = array())
    {
        // try to find appropriate entity
        if ($entity) {
            $entityAttributeName = null;
            $managedEntityAttribute = $this->getManagedEntityAttributeByEntity($workflow, $entity);
            if ($managedEntityAttribute) {
                $entityAttributeName = $managedEntityAttribute->getName();
            }

            if (!$entityAttributeName) {
                throw new UnknownAttributeException(
                    sprintf(
                        'Can\'t find attribute for managed entity in workflow "%s"',
                        $workflow->getName()
                    )
                );
            }

            $data[$entityAttributeName] = $entity;
        }

        return $data;
    }

    /**
     * @param Workflow $workflow
     * @param object $entity
     * @return null|Attribute
     */
    protected function getManagedEntityAttributeByEntity(Workflow $workflow, $entity)
    {
        $entityClass = $this->doctrineHelper->getEntityClass($entity);

        /** @var Attribute $attribute */
        foreach ($workflow->getManagedEntityAttributes() as $attribute) {
            if ($attribute->getOption('class') == $entityClass) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Get workflow instance.
     *
     * string - workflow name
     * WorkflowItem - getWorkflowName() method will be used to get workflow
     * Workflow - will be returned by itself
     *
     * @param string|Workflow|WorkflowItem $workflowIdentifier
     * @throws WorkflowException
     * @return Workflow
     */
    public function getWorkflow($workflowIdentifier)
    {
        if (is_string($workflowIdentifier)) {
            return $this->workflowRegistry->getWorkflow($workflowIdentifier);
        } elseif ($workflowIdentifier instanceof WorkflowItem) {
            return $this->workflowRegistry->getWorkflow($workflowIdentifier->getWorkflowName());
        } elseif ($workflowIdentifier instanceof Workflow) {
            return $workflowIdentifier;
        }

        throw new WorkflowException('Can\'t find workflow by given identifier.');
    }
}
