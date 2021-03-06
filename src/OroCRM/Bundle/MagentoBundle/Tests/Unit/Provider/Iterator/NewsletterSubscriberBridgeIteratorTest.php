<?php

namespace OroCRM\Bundle\MagentoBundle\Tests\Unit\Provider\Iterator;

use OroCRM\Bundle\MagentoBundle\Provider\Iterator\NewsletterSubscriberBridgeIterator;
use OroCRM\Bundle\MagentoBundle\Provider\Iterator\UpdatedLoaderInterface;
use OroCRM\Bundle\MagentoBundle\Provider\Transport\MagentoTransportInterface;

class NewsletterSubscriberBridgeIteratorTest extends BaseIteratorTestCase
{
    /**
     * @var NewsletterSubscriberBridgeIterator
     */
    protected $iterator;

    protected function setUp()
    {
        parent::setUp();

        $this->iterator = new NewsletterSubscriberBridgeIterator($this->transport, $this->settings);
    }

    /**
     * @param array $data
     * @param array $stores
     *
     * @dataProvider dataProvider
     */
    public function testIteration(array $data, array $stores)
    {
        $dependencies = [MagentoTransportInterface::ALIAS_STORES => $stores];
        $this->transport->expects($this->atLeastOnce())
            ->method('getDependencies')
            ->will($this->returnValue($dependencies));

        $this->transport->expects($this->atLeastOnce())->method('call')
            ->with($this->equalTo('newsletterSubscriberList'))
            ->will($this->returnValue($data));

        $this->assertEquals(
            [
                1 => array_merge((array)$data[0], ['store' => $stores[1]]),
                2 => array_merge((array)$data[1], ['store' => $stores[1]]),
                3 => array_merge((array)$data[2], ['store' => $stores[1]])
            ],
            iterator_to_array($this->iterator)
        );
    }

    /**
     * @param array $data
     * @param array $stores
     *
     * @dataProvider dataProvider
     */
    public function testIterationWithInitialId(array $data, array $stores)
    {
        $dependencies = [MagentoTransportInterface::ALIAS_STORES => $stores];
        $this->transport->expects($this->atLeastOnce())
            ->method('getDependencies')
            ->will($this->returnValue($dependencies));

        $this->iterator->setInitialId(time());

        $this->transport->expects($this->once())->method('call')
            ->with($this->equalTo('newsletterSubscriberList'))
            ->will($this->returnValue($data));

        $this->assertEquals(
            [
                1 => array_merge((array)$data[0], ['store' => $stores[1]]),
                2 => array_merge((array)$data[1], ['store' => $stores[1]]),
                3 => array_merge((array)$data[2], ['store' => $stores[1]])
            ],
            iterator_to_array($this->iterator)
        );
    }

    /**
     * @param array $data
     * @param array $stores
     *
     * @dataProvider dataProvider
     */
    public function testInitialMode(array $data, array $stores)
    {
        $this->iterator->setMode(UpdatedLoaderInterface::IMPORT_MODE_INITIAL);
        $this->testIteration($data, $stores);
    }

    /**
     * @param array $data
     * @param array $stores
     *
     * @dataProvider dataProvider
     */
    public function testInitialModeWithInitialId(array $data, array $stores)
    {
        $this->iterator->setMode(UpdatedLoaderInterface::IMPORT_MODE_INITIAL);
        $this->testIterationWithInitialId($data, $stores);
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return [
            'one test case' => [
                [
                    (object)[
                        'subscriber_id' => 1,
                        'change_status_at' => (array)new \DateTime(),
                        'customer_id' => 2,
                        'store_id' => 1,
                        'subscriber_email' => 'email1@example.com',
                        'subscriber_status' => 1,
                        'subscriber_confirm_code' => uniqid()
                    ],
                    (object)[
                        'subscriber_id' => 2,
                        'change_status_at' => (array)new \DateTime(),
                        'customer_id' => 3,
                        'store_id' => 1,
                        'subscriber_email' => 'email2@example.com',
                        'subscriber_status' => 2,
                        'subscriber_confirm_code' => uniqid()
                    ],
                    (object)[
                        'subscriber_id' => 3,
                        'change_status_at' => (array)new \DateTime(),
                        'customer_id' => 4,
                        'store_id' => 1,
                        'subscriber_email' => 'email3@example.com',
                        'subscriber_status' => 3,
                        'subscriber_confirm_code' => uniqid()
                    ]
                ],
                [
                    1 => [
                        'id' => 1,
                        'code' => 'admin',
                        'name' => 'Admin'
                    ]
                ]
            ]
        ];
    }
}
