<?php

namespace macfly\tracker\components;

use Yii;
use yii\base\Component;
use yii\web\Request;
use yii\base\Event;

class Tracker extends Component
{
    public $header  = 'X-tracker-request-id';

    protected $id   = null;

    public function init()
    {
        parent::init();

        $header = $this->header;

        // Get id from HTTP request
        $request = Yii::$app->getRequest();
        if ($request instanceof Request && ($this->id = Yii::$app->request->headers->get($header)) !== null) {
            Yii::info(sprintf("Get tracker id from HTTP header '%s'", $header));
        }

        // Get id from AMQP message
        if (Yii::$app->has('rabbitmq') && ($rabbitmq = Yii::$app->get('rabbitmq')) instanceof \mikemadisonweb\rabbitmq\Configuration) {
            $that = $this;
            $rabbitmq->on(constant('\mikemadisonweb\rabbitmq\components\RabbitMQConsumerEvent::BEFORE_CONSUME'), function ($event) use ($header, $that) {
                if ($event->message->has('application_headers')) {
                    $headers = $event->message->get('application_headers')->getNativeData();
                    if (($id = \yii\helpers\ArrayHelper::getValue($headers, $header)) !== null) {
                        $that->id = $id;
                        \Yii::info(sprintf("Get tracker id from AMQP header '%s'", $header));
                    }
                }
            });
        }

        $id = $this->getId();

        // Set id to out going HTTP request
        if (class_exists('\yii\httpclient\Client')) {
            Event::on('\yii\httpclient\Client', constant('\yii\httpclient\Client::EVENT_BEFORE_SEND'), function ($event) use ($id, $header) {
                $event->request->addHeaders([$header => $id]);
            });
        }

        // Set id to out going AMQP message
        if (Yii::$app->has('rabbitmq') && ($rabbitmq = Yii::$app->get('rabbitmq')) instanceof \mikemadisonweb\rabbitmq\Configuration) {
            $rabbitmq->on(constant('\mikemadisonweb\rabbitmq\components\RabbitMQPublisherEvent::BEFORE_PUBLISH'), function ($event) use ($id, $header) {
                // Get headers if exist or create one if none
                $headers = $event->message->has('application_headers') ? $event->message->get('application_headers') : new \PhpAmqpLib\Wire\AMQPTable();
                $headers->set($header, $id);
                $event->message->set('application_headers', $headers);
            });
        }
    }

    public function getId()
    {
        if ($this->id === null) {
            $this->id = hash('sha256', uniqid(Yii::$app->name, true));
        }

        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }
}
