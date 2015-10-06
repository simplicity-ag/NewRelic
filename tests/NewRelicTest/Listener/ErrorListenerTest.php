<?php
namespace NewRelicTest\Listener;

use Exception;
use NewRelic\Listener\ErrorListener;
use NewRelic\ModuleOptions;
use Zend\EventManager\EventManager;
use Zend\Log\Logger;
use Zend\Log\Writer\Mock as LogWriter;
use Zend\Mvc\MvcEvent;

class ErrorListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ErrorListener
     */
    protected $listener;
    
    /**
     * @var LogWriter
     */
    protected $writer;

    public function setUp()
    {
        $logger = new Logger();
        $writers = $logger->getWriters();
        $this->writer = new LogWriter();
        $writers->insert($this->writer, 1);

        $this->listener = new ErrorListener($logger);
    }

    public function testListenerAttached()
    {
        $events = new EventManager();
        $events->attach($this->listener);

        $listeners = $events->getListeners(MvcEvent::EVENT_DISPATCH_ERROR);
        $this->assertEquals(1, count($listeners));
        $listeners = $events->getListeners(MvcEvent::EVENT_RENDER_ERROR);
        $this->assertEquals(1, count($listeners));

        $events->detach($this->listener);

        $listeners = $events->getListeners(MvcEvent::EVENT_DISPATCH_ERROR);
        $this->assertEquals(0, count($listeners));
        $listeners = $events->getListeners(MvcEvent::EVENT_RENDER_ERROR);
        $this->assertEquals(0, count($listeners));
    }

    public function testEventHandlerCallLogger()
    {
        $this->moduleOptions = new ModuleOptions();
        $this->listener->setModuleOptions($this->moduleOptions);

        $this->moduleOptions->setExceptionsLoggingEnabled(true);

        $mvcEvent = new MvcEvent();

        $exception = new Exception('a message');
        $mvcEvent->setParam('exception', $exception);

        $this->listener->onError($mvcEvent);

        $event = $this->writer->events[0];

        $this->assertContains($exception->getFile(), $event['message']);
        $this->assertContains((string) $exception->getLine(), $event['message']);
        $this->assertContains($exception->getMessage(), $event['message']);
        $this->assertSame($exception, $event['extra']['exception']);
    }

    public function testEventHandlerNotCallLogger()
    {
        $this->moduleOptions = new ModuleOptions();
        $this->listener->setModuleOptions($this->moduleOptions);

        $this->moduleOptions->setExceptionsLoggingEnabled(false);

        $mvcEvent = new MvcEvent();

        $exception = new Exception('a message');
        $mvcEvent->setParam('exception', $exception);

        $this->listener->onError($mvcEvent);

        $this->assertCount(0, $this->writer->events);
    }
}
