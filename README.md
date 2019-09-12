# yii2-tracker

Yii2 tracker try to get or set a unique request id for every request to follow it from service to service mainly for logging, debuging and track which micro-service is doing what on other micro-service app.

Component will try to get from :

* From every [HTTP request](http://www.yiiframework.com/doc-2.0/guide-runtime-requests.html) the header with name @X-tracker-request-id@, it means that we have been call by another service which already provide us a unique request id
* From RabbitMQ message header, currently working with [mikemadisonweb/yii2-rabbitmq extension](https://github.com/mikemadisonweb/yii2-rabbitmq)
* If no unique request id is provide create one it means we're the first micro-service to be call
* If we're doing request to API through [yiisoft/yii2-httpclient](https://github.com/yiisoft/yii2-httpclient/) add the @X-tracker-request-id@ to outgoing request
* If we're sending RabbitMQ message add to every message a header @X-tracker-request-id@, working with version 2.x of [mikemadisonweb/yii2-rabbitmq extension](https://github.com/mikemadisonweb/yii2-rabbitmq/)
* Add header @X-tracker-request-id@ in every [HTTP response](http://www.yiiframework.com/doc-2.0/guide-runtime-responses.html)

Installation
------------

The preferred way to install this component is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist "macfly/yii2-tracker" "*"
```

or add to the @require@ section in your `composer.json` file.

```
"macfly/yii2-tracker": "*"
```

Configure
------------

Configure **config/web.php** and **config/console.php** as follows

```php
'bootstrap'     => [
    'log',
    'tracker',
],
'components'    => [
    'tracker'   => [
        'class'  => 'app\components\Tracker',
        'header' => 'X-tracker-request-id', // Name of the header component try to get or set.
    ],
    ................
],
```

Usage
------------

You can get the unique request id from the component with :

```php
 \Yii::$app->tracker->id;
```

For example if you want to add it to your log you can change the the target prefix to :

```php
'components' => [
    'log' => [
        'targets' => [
            [
                'class'   => 'yii\log\FileTarget',
                'logVars' => [],
                'prefix' => function ($message) {
                    if (Yii::$app === null) {
                        return '';
                    }

                    $app = Yii::$app->name;
                    $id = Yii::$app->tracker->getId();
                    $request = Yii::$app->getRequest();
                    $ip = $request instanceof Request ? $request->getUserIP() : '-';
                    /* @var $user \yii\web\User */
                    $user = Yii::$app->has('user', true) ? Yii::$app->get('user') : null;
                    if ($user && ($identity = $user->getIdentity(false))) {
                        $userID = $identity->getId();
                    } else {
                        $userID = '-';
                    }
                    /* @var $session \yii\web\Session */
                    $session = Yii::$app->has('session', true) ? Yii::$app->get('session') : null;
                    $sessionID = $session && $session->getIsActive() ? $session->getId() : '-';


                    return "[$app][$id][$ip][$userID][$sessionID]";
                },
            ],
        ],
    ],
```
