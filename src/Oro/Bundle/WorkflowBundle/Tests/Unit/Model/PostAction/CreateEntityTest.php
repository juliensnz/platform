<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\PostAction;

use Symfony\Component\PropertyAccess\PropertyPath;

use Oro\Bundle\WorkflowBundle\Model\PostAction\CreateEntity;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\ItemStub;
use Oro\Bundle\WorkflowBundle\Model\ContextAccessor;

class CreateEntityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CreateEntity
     */
    protected $postAction;

    /**
     * @var ContextAccessor
     */
    protected $contextAccessor;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    protected function setUp()
    {
        $this->contextAccessor = new ContextAccessor();

        $this->registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')
            ->disableOriginalConstructor()
            ->getMock();

        $this->postAction = new CreateEntity($this->contextAccessor, $this->registry);
    }

    protected function tearDown()
    {
        unset($this->contextAccessor);
        unset($this->registry);
        unset($this->postAction);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Class name parameter is required
     */
    public function testInitializeExceptionNoClassName()
    {
        $this->postAction->initialize(array('some' => 1, 'attribute' => $this->getPropertyPath()));
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute name parameter is required
     */
    public function testInitializeExceptionNoAttribute()
    {
        $this->postAction->initialize(array('class' => 'stdClass'));
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute must be valid property definition.
     */
    public function testInitializeExceptionInvalidAttribute()
    {
        $this->postAction->initialize(array('class' => 'stdClass', 'attribute' => 'string'));
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Entity data must be an array.
     */
    public function testInitializeExceptionInvalidData()
    {
        $this->postAction->initialize(
            array('class' => 'stdClass', 'attribute' => $this->getPropertyPath(), 'data' => 'string_value')
        );
    }

    public function testInitialize()
    {
        $options = array('class' => 'stdClass', 'attribute' => $this->getPropertyPath());
        $this->assertEquals($this->postAction, $this->postAction->initialize($options));
        $this->assertAttributeEquals($options, 'options', $this->postAction);
    }

    /**
     * @param array $options
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options)
    {
        $em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf($options['class']));
        $em->expects($this->once())
            ->method('flush')
            ->with($this->isInstanceOf($options['class']));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($options['class'])
            ->will($this->returnValue($em));

        $context = new ItemStub(array());
        $attributeName = (string)$options['attribute'];
        $this->postAction->initialize($options);
        $this->postAction->execute($context);
        $this->assertNotNull($context->$attributeName);
        $this->assertInstanceOf($options['class'], $context->$attributeName);

        /** @var ItemStub $entity */
        $entity = $context->$attributeName;
        $expectedData = !empty($options['data']) ? $options['data'] : array();
        $this->assertInstanceOf($options['class'], $entity);
        $this->assertEquals($expectedData, $entity->getData());
    }

    /**
     * @return array
     */
    public function executeDataProvider()
    {
        return array(
            'without data' => array(
                'options' => array(
                    'class'     => 'Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\ItemStub',
                    'attribute' => new PropertyPath('test_attribute'),
                )
            ),
            'with data' => array(
                'options' => array(
                    'class'     => 'Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\ItemStub',
                    'attribute' => new PropertyPath('test_attribute'),
                    'data'      => array('key1' => 'value1', 'key2' => 'value2'),
                )
            ),
        );
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\NotManageableEntityException
     * @expectedExceptionMessage Entity class "stdClass" is not manageable.
     */
    public function testExecuteEntityNotManageable()
    {
        $options = array('class' => 'stdClass', 'attribute' => $this->getPropertyPath());
        $context = array();
        $this->postAction->initialize($options);
        $this->postAction->execute($context);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\PostActionException
     * @expectedExceptionMessage Can't create entity stdClass. Test exception.
     */
    public function testExecuteCantCreateEntity()
    {
        $em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();
        $em->expects($this->once())
            ->method('persist')
            ->will(
                $this->returnCallback(
                    function () {
                        throw new \Exception('Test exception.');
                    }
                )
            );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->will($this->returnValue($em));

        $options = array('class' => 'stdClass', 'attribute' => $this->getPropertyPath());
        $context = array();
        $this->postAction->initialize($options);
        $this->postAction->execute($context);
    }

    protected function getPropertyPath()
    {
        return $this->getMockBuilder('Symfony\Component\PropertyAccess\PropertyPath')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
