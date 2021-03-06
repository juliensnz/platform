<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\ConfigBundle\Provider\FormProvider;
use Oro\Bundle\ConfigBundle\DependencyInjection\Compiler\SystemConfigurationPass;

class SystemConfigurationPassTest extends \PHPUnit_Framework_TestCase
{
    /** @var SystemConfigurationPass */
    protected $compiler;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $container;

    public function setUp()
    {
        $this->compiler  = new SystemConfigurationPass();
        $this->container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()->getMock();
    }

    public function tearDown()
    {
        unset($this->compiler);
        unset($this->container);
    }

    /**
     * @dataProvider bundlesProvider
     */
    public function testProcess(array $bundles, $expectedSet)
    {
        $this->container->expects($this->once())->method('getParameter')->with('kernel.bundles')
            ->will($this->returnValue($bundles));
        if ($expectedSet) {
            $taggedServices = array('some.service.id' => 'some arguments');

            $this->container->expects($this->once())->method('findTaggedServiceIds')->with(FormProvider::TAG_NAME)
                ->will($this->returnValue($taggedServices));

            $definitionMock = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')
                ->disableOriginalConstructor()->getMock();
            $this->container->expects($this->exactly(count($taggedServices)))->method('getDefinition')
                ->will($this->returnValue($definitionMock));

            $definitionMock->expects($this->exactly(count($taggedServices)))->method('replaceArgument')
                ->with($this->equalTo(0));
        }

        $this->compiler->process($this->container);
    }

    /**
     * @return array
     */
    public function bundlesProvider()
    {
        return array(
            'no one bundle specified config' => array(
                'bundles'         => array(),
                'should set data' => false
            ),
            'one bundle specified config'    => array(
                'bundles'         => array('Oro\Bundle\ConfigBundle\Tests\Unit\Fixtures\TestBundle'),
                'should set data' => true
            )
        );
    }
}
