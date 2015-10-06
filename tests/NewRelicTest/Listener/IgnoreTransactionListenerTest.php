<?php
namespace NewRelicTest\Listener;

use NewRelic\ClientInterface;
use NewRelic\Listener\IgnoreTransactionListener;
use Zend\Mvc\MvcEvent;

class IgnoreTransactionListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClientInterface 
     */
    protected $client;

    /**
     * @var string
     */
    protected $currentRoute = 'foo/bar';

    /**
     * @var string
     */
    protected $currentController = 'FooController';

    /**
     * @var string
     */
    protected $currentAction = 'foo';

    public function setUp()
    {
        $this->client = $this->getMockBuilder('NewRelic\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('ignoreTransaction'))
            ->getMock();
    }

    /**
     * @param  array $transactions
     * @return IgnoreTransactionListener
     */
    protected function getListener($transactions)
    {
        $listener = new IgnoreTransactionListener($transactions);
        $listener->setClient($this->client);

        return $listener;
    }

    /**
     * @return MvcEvent
     */
    protected function getEvent()
    {
        $event = new MvcEvent();
        
        $routeMatch = $this->getMockBuilder('Zend\Mvc\Router\RouteMatch')
            ->disableOriginalConstructor()
            ->getMock();
        $event->setRouteMatch($routeMatch);

        $routeMatch
            ->expects($this->any())
            ->method('getMatchedRouteName')
            ->will($this->returnValue($this->currentRoute));

        $controller = $this->currentController;
        $action = $this->currentAction;
        $callback = function($name) use ($controller, $action) {
            if ($name == 'controller') {
                return $controller;
            }
            if ($name == 'action') {
                return $action;
            }
        };

        $routeMatch
            ->expects($this->any())
            ->method('getParam')
            ->will($this->returnCallback($callback));

        return $event;
    }

    /**
     * @dataProvider ignoreTransactionProvider
     */
    public function testIgnoreTransaction($transactions, $executed)
    {
        if ($executed) {
            $this->client
                ->expects($this->once())
                ->method('ignoreTransaction');
        } else {
            $this->client
                ->expects($this->never())
                ->method('ignoreTransaction');
        }
        
        $listener = $this->getListener($transactions);

        $event = $this->getEvent();

        $listener->onRequest($event);
    }

    public function ignoreTransactionProvider()
    {
        return array(
            // Client method called
            array(
                array('routes' => array('foo/bar')),
                true,
            ),
            array(
                array('routes' => array('foo*')),
                true,
            ),
            array(
                array('routes' => array('*')),
                true,
            ),
            array(
                array('controllers' => array(
                    'FooController'
                )),
                true,
            ),
            array(
                array('controllers' => array(
                    array('FooController', array('foo'))
                )),
                true,
            ),

            // Client method avoided
            array(
                array('routes' => array('bar/foo')),
                false,
            ),
            array(
                array('controllers' => array(
                    'BarController'
                )),
                false,
            ),
            array(
                array('controllers' => array(
                    array('FooController', array('bar'))
                )),
                false,
            ),
        );
    }
}
