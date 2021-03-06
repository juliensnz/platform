<?php

namespace Oro\Bundle\BatchBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
use Oro\Bundle\BatchBundle\Event\JobExecutionEvent;
use Oro\Bundle\BatchBundle\Event\EventInterface;
use Oro\Bundle\BatchBundle\Event\StepExecutionEvent;
use Oro\Bundle\BatchBundle\Entity\StepExecution;

/**
 * Subscriber to log job execution result
 *
 */
class LoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * @var integer $readerWarningCount
     */
    private $readerWarningCount = 0;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            EventInterface::BEFORE_JOB_EXECUTION       => 'beforeJobExecution',
            EventInterface::JOB_EXECUTION_STOPPED      => 'jobExecutionStopped',
            EventInterface::JOB_EXECUTION_INTERRUPTED  => 'jobExecutionInterrupted',
            EventInterface::JOB_EXECUTION_FATAL_ERROR  => 'jobExecutionFatalError',
            EventInterface::BEFORE_JOB_STATUS_UPGRADE  => 'beforeJobStatusUpgrade',
            EventInterface::BEFORE_STEP_EXECUTION      => 'beforeStepExecution',
            EventInterface::STEP_EXECUTION_SUCCEEDED   => 'stepExecutionSucceeded',
            EventInterface::STEP_EXECUTION_INTERRUPTED => 'stepExecutionInterrupted',
            EventInterface::STEP_EXECUTION_ERRORED     => 'stepExecutionErrored',
            EventInterface::STEP_EXECUTION_COMPLETED   => 'stepExecutionCompleted',
            EventInterface::INVALID_READER_EXECUTION   => 'invalidReaderExecution',
        );
    }

    /**
     * Log the job execution before the job execution
     *
     * @param JobExecutionEvent $event
     */
    public function beforeJobExecution(JobExecutionEvent $event)
    {
        $jobExecution = $event->getJobExecution();

        $this->logger->debug(sprintf('Job execution starting: %s', $jobExecution));
    }

    /**
     * Log the job execution when the job execution stopped
     *
     * @param JobExecutionEvent $event
     */
    public function jobExecutionStopped(JobExecutionEvent $event)
    {
        $jobExecution = $event->getJobExecution();

        $this->logger->debug(sprintf('Job execution was stopped: %s', $jobExecution));
    }

    /**
     * Log the job execution when the job execution was interrupted
     *
     * @param JobExecutionEvent $event
     */
    public function jobExecutionInterrupted(JobExecutionEvent $event)
    {
        $jobExecution = $event->getJobExecution();

        $this->logger->info(sprintf('Encountered interruption executing job: %s', $jobExecution));
        $this->logger->debug('Full exception', array('exception', $jobExecution->getFailureExceptions()));
    }

    /**
     * Log the job execution when a fatal error was raised during job execution
     *
     * @param JobExecutionEvent $event
     */
    public function jobExecutionFatalError(JobExecutionEvent $event)
    {
        $jobExecution = $event->getJobExecution();

        $this->logger->error(
            'Encountered fatal error executing job',
            array('exception', $jobExecution->getFailureExceptions())
        );
    }

    /**
     * Log the job execution before its status is upgraded
     *
     * @param JobExecutionEvent $event
     */
    public function beforeJobStatusUpgrade(JobExecutionEvent $event)
    {
        $jobExecution = $event->getJobExecution();

        $this->logger->debug(sprintf('Upgrading JobExecution status: %s', $jobExecution));
    }

    /**
     * Log the step execution before the step execution
     *
     * @param StepExecutionEvent $event
     */
    public function beforeStepExecution(StepExecutionEvent $event)
    {
        $stepExecution = $event->getStepExecution();

        $this->logger->info(sprintf('Step execution starting: %s', $stepExecution));
    }

    /**
     * Log the step execution when the step execution succeeded
     *
     * @param StepExecutionEvent $event
     */
    public function stepExecutionSucceeded(StepExecutionEvent $event)
    {
        $stepExecution = $event->getStepExecution();

        $this->logger->debug(sprintf('Step execution success: id= %d', $stepExecution->getId()));
    }

    /**
     * Log the step execution when the step execution was interrupted
     *
     * @param StepExecutionEvent $event
     */
    public function stepExecutionInterrupted(StepExecutionEvent $event)
    {
        $stepExecution = $event->getStepExecution();

        $this->logger->info(
            sprintf('Encountered interruption executing step: %s', $stepExecution->getFailureExceptionMessages())
        );
        $this->logger->debug('Full exception', array('exception', $stepExecution->getFailureExceptions()));
    }

    /**
     * Log the step execution when the step execution was errored
     *
     * @param StepExecutionEvent $event
     */
    public function stepExecutionErrored(StepExecutionEvent $event)
    {
        $stepExecution = $event->getStepExecution();

        $this->logger->error(
            sprintf('Encountered an error executing the step: %s', $stepExecution->getFailureExceptionMessages())
        );
    }

    /**
     * Log the step execution when the step execution was completed
     *
     * @param StepExecutionEvent $event
     */
    public function stepExecutionCompleted(StepExecutionEvent $event)
    {
        $stepExecution = $event->getStepExecution();

        $this->logger->debug(sprintf('Step execution complete: %s', $stepExecution));
    }

    /**
     * Log the step execution when the reader execution was invalid
     *
     * @param StepExecutionEvent $event
     */
    public function invalidReaderExecution(StepExecutionEvent $event)
    {
        $stepExecution  = $event->getStepExecution();
        $readerWarnings = $stepExecution->getReaderWarnings();

        if (count($readerWarnings) <= $this->readerWarningCount) {
            return;
        }

        $lastWarning = end($readerWarnings);
        $this->readerWarningCount++;

        $this->logger->warning(
            sprintf(
                'The %s was unable to handle the following data: %s (REASON: %s).',
                get_class($lastWarning['reader']),
                $this->formatAsString($lastWarning['data']),
                $lastWarning['reason']
            )
        );
    }

    /**
     * Format anything as a string
     *
     * @param mixed $data
     *
     * @return string
     */
    private function formatAsString($data)
    {
        if (is_array($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[] = sprintf(
                    '%s => %s',
                    $this->formatAsString($key),
                    $this->formatAsString($value)
                );
            }

            return sprintf("[%s]", join(', ', $result));
        }

        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }

        return (string) $data;
    }
}
