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

        // Get id from HTTP request
        $request = Yii::$app->getRequest();
        if ($request instanceof Request && ($this->id = Yii::$app->request->headers->get($this->header)) !== null) {
            Yii::info(sprintf("Get tracker id from HTTP header '%s'", $this->header));
        }

        // Get id from AMQP message
        if (Yii::$app->has('rabbitmq') && ($rabbitmq = Yii::$app->get('rabbitmq')) instanceof \mikemadisonweb\rabbitmq\Configuration) {
            $rabbitmq->on(constant('\mikemadisonweb\rabbitmq\events\RabbitMQConsumerEvent::BEFORE_CONSUME'), function ($event) {
                if ($event->message->has('application_headers')) {
                    $headers = $event->message->get('application_headers')->getNativeData();
                    if (($id = \yii\helpers\ArrayHelper::getValue($headers, Yii::$app->tracker->header)) !== null) {
                        Yii::$app->tracker->id = $id;
                        \Yii::info(sprintf("Get tracker id from AMQP header '%s'", Yii::$app->tracker->header));
                    }
                }
            });
        }

        // Set id to out going HTTP Client request
        if (class_exists('\yii\httpclient\Client')) {
            Event::on('\yii\httpclient\Client', constant('\yii\httpclient\Client::EVENT_BEFORE_SEND'), function ($event) {
                if(!$event->request->headers->has(Yii::$app->tracker->header)) {
                    $event->request->addHeaders([Yii::$app->tracker->header => Yii::$app->tracker->getId()]);
                }
            });
        }

        // Set id to out going AMQP message
        if (Yii::$app->has('rabbitmq') && ($rabbitmq = Yii::$app->get('rabbitmq')) instanceof \mikemadisonweb\rabbitmq\Configuration) {
            $rabbitmq->on(constant('\mikemadisonweb\rabbitmq\events\RabbitMQPublisherEvent::BEFORE_PUBLISH'), function ($event) {
                // Get headers if exist or create one if none
                $headers = $event->message->has('application_headers') ? $event->message->get('application_headers') : new \PhpAmqpLib\Wire\AMQPTable();
                $headers->set(Yii::$app->tracker->header, Yii::$app->tracker->getId());
                $event->message->set('application_headers', $headers);
            });
        }

        // Set id to out going HTTP response
        if (class_exists('\yii\web\Response')) {
            Event::on('\yii\web\Response', constant('\yii\web\Response::EVENT_BEFORE_SEND'), function ($event)
            {
                if(!$event->sender->headers->has(Yii::$app->tracker->header)) {
                    $event->sender->headers->set(Yii::$app->tracker->header, Yii::$app->tracker->getId());
                }
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
