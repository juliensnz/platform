<?php

namespace Oro\Bundle\ImapBundle\Mail\Storage;

class Imap extends \Zend\Mail\Storage\Imap
{
    const RFC822_HEADER = 'RFC822.HEADER';
    const FLAGS = 'FLAGS';
    const UID = 'UID';

    /**
     * UIDVALIDITY of currently selected folder
     *
     * @var int
     */
    protected $uidValidity;

    /**
     * Items to be returned by getMessage
     */
    protected $getMessageItems;

    /**
     * This flag is used to prevent closing the default storage socket
     * There is only one case when this flag set to true, when the storage is created
     * based on another storage and we want to use the already opened socket of the base storage
     * See the constructor of this class for details
     *
     * @var bool
     */
    private $ignoreCloseCommand = false;

    /**
     * {@inheritdoc}
     */
    public function __construct($params)
    {
        if ($params instanceof Imap) {
            $params->ignoreCloseCommand = true;
            $this->currentFolder = $params->currentFolder;
            $params = $params->protocol;
        }

        parent::__construct($params);
        $this->messageClass = 'Oro\Bundle\ImapBundle\Mail\Storage\Message';
        $this->getMessageItems = array(self::FLAGS, self::RFC822_HEADER, self::UID);
    }

    /**
     * Get capabilities from IMAP server
     *
     * @return string[] list of capabilities
     */
    public function capability()
    {
        return $this->protocol->capability();
    }

    /**
     * Gets UIDVALIDITY of currently selected folder
     *
     * @return int
     */
    public function getUidValidity()
    {
        return $this->uidValidity;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage($id)
    {
        $data = $this->protocol->fetch($this->getMessageItems, $id);
        $header = $data[self::RFC822_HEADER];

        $flags = array();
        foreach ($data[self::FLAGS] as $flag) {
            $flags[] = isset(static::$knownFlags[$flag]) ? static::$knownFlags[$flag] : $flag;
        }

        /** @var \Zend\Mail\Storage\Message $message */
        $message = new $this->messageClass(array(
            'handler' => $this,
            'id' => $id,
            'headers' => $header,
            'flags' => $flags
        ));

        $headers = $message->getHeaders();
        $this->setExtHeaders($headers, $data);

        return $message;
    }

    /**
     * Searches messages by the given criteria
     *
     * @param array $criteria The search criteria
     * @return string[] Message ids
     * @throws \Zend\Mail\Storage\Exception\RuntimeException
     */
    public function search(array $criteria)
    {
        if (empty($criteria)) {
            throw new \Zend\Mail\Storage\Exception\RuntimeException('The search criteria must not be empty.');
        }

        $response = $this->protocol->search($criteria);
        if (!is_array($response)) {
            throw new \Zend\Mail\Storage\Exception\RuntimeException('Cannot search messages.');
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function selectFolder($globalName)
    {
        if ((string)$this->currentFolder === (string)$globalName) {
            // The given folder already selected
            return;
        }

        $this->currentFolder = $globalName;
        $selectResponse = $this->protocol->select($this->currentFolder);
        if (!$selectResponse) {
            $this->currentFolder = '';
            throw new \Zend\Mail\Storage\Exception\RuntimeException('cannot change folder, maybe it does not exist');
        }

        $this->uidValidity = $selectResponse['uidvalidity'];
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->ignoreCloseCommand) {
            return;
        }

        parent::close();
    }

    /**
     * Sets additional message headers
     *
     * @param \Zend\Mail\Headers $headers
     * @param array $data
     */
    protected function setExtHeaders(&$headers, array $data)
    {
        $headers->addHeaderLine('UID', $data['UID']);
    }
}
