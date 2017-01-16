<?php

use Carbon\Carbon;
use Codeception\Util\Stub;
use MailPoet\API\Endpoints\Cron;
use MailPoet\Cron\CronHelper;
use MailPoet\Cron\Workers\SendingServiceKeyCheck as SSKeyCheck;
use MailPoet\Cron\Workers\SendingServiceKeyCheck\API;
use MailPoet\Mailer\Mailer;
use MailPoet\Models\SendingQueue;
use MailPoet\Models\Setting;
use MailPoet\Services\Bridge;
use MailPoet\Util\Helpers;

class SendingServiceKeyCheckTest extends MailPoetTest {
  function _before() {
    $this->emails = array(
      'soft_bounce@example.com',
      'hard_bounce@example.com',
      'good_address@example.com'
    );

    $this->sskeycheck = new SSKeyCheck(microtime(true));
  }

  function testItConstructs() {
    expect($this->sskeycheck->timer)->notEmpty();
  }

  function testItThrowsExceptionWhenExecutionLimitIsReached() {
    try {
      $sskeycheck = new SSKeyCheck(microtime(true) - CronHelper::DAEMON_EXECUTION_LIMIT);
      self::fail('Maximum execution time limit exception was not thrown.');
    } catch(\Exception $e) {
      expect($e->getMessage())->equals('Maximum execution time has been reached.');
    }
  }

  function testItSchedulesSendingServiceKeyCheck() {
    expect(SendingQueue::where('type', SSKeyCheck::TASK_TYPE)->findMany())->isEmpty();
    SSKeyCheck::schedule();
    expect(SendingQueue::where('type', SSKeyCheck::TASK_TYPE)->findMany())->notEmpty();
  }

  function testItDoesNotScheduleSendingServiceKeyCheckTwice() {
    expect(count(SendingQueue::where('type', SSKeyCheck::TASK_TYPE)->findMany()))->equals(0);
    SSKeyCheck::schedule();
    expect(count(SendingQueue::where('type', SSKeyCheck::TASK_TYPE)->findMany()))->equals(1);
    SSKeyCheck::schedule();
    expect(count(SendingQueue::where('type', SSKeyCheck::TASK_TYPE)->findMany()))->equals(1);
  }

  function testItCanGetScheduledQueues() {
    expect(SSKeyCheck::getScheduledQueues())->isEmpty();
    $this->createScheduledQueue();
    expect(SSKeyCheck::getScheduledQueues())->notEmpty();
  }

  function testItCanGetRunningQueues() {
    expect(SSKeyCheck::getRunningQueues())->isEmpty();
    $this->createRunningQueue();
    expect(SSKeyCheck::getRunningQueues())->notEmpty();
  }

  function testItCanGetAllDueQueues() {
    expect(SSKeyCheck::getAllDueQueues())->isEmpty();

    // scheduled for now
    $this->createScheduledQueue();

    // running
    $this->createRunningQueue();

    // scheduled in the future (should not be retrieved)
    $queue = $this->createScheduledQueue();
    $queue->scheduled_at = Carbon::createFromTimestamp(current_time('timestamp'))->addDays(7);
    $queue->save();

    // completed (should not be retrieved)
    $queue = $this->createRunningQueue();
    $queue->status = SendingQueue::STATUS_COMPLETED;
    $queue->save();

    expect(count(SSKeyCheck::getAllDueQueues()))->equals(2);
  }

  function testItCanGetFutureQueues() {
    expect(SSKeyCheck::getFutureQueues())->isEmpty();
    $queue = $this->createScheduledQueue();
    $queue->scheduled_at = Carbon::createFromTimestamp(current_time('timestamp'))->addDays(7);
    $queue->save();
    expect(count(SSKeyCheck::getFutureQueues()))->notEmpty();
  }

  function testItFailsToProcessWithoutMailPoetMethodSetUp() {
    expect($this->sskeycheck->process())->false();
  }

  function testItFailsToProcessWithoutQueues() {
    $this->setMailPoetSendingMethod();
    expect($this->sskeycheck->process())->false();
  }

  function testItProcesses() {
    $this->setMailPoetSendingMethod();
    $this->createScheduledQueue();
    $this->createRunningQueue();
    expect($this->sskeycheck->process())->true();
  }

  function testItPreparesSendingServiceKeyCheckQueue() {
    $queue = $this->createScheduledQueue();
    $this->sskeycheck->prepareQueue($queue);
    expect($queue->status)->null();
  }

  function testItProcessesSSKeyCheckQueue() {
    $this->sskeycheck->bridge = Stub::make(
      new Bridge,
      array('checkKey' => array('code' => Bridge::MAILPOET_KEY_VALID)),
      $this
    );
    $this->setMailPoetSendingMethod();
    $queue = $this->createRunningQueue();
    $this->sskeycheck->prepareQueue($queue);
    $this->sskeycheck->processQueue($queue);
    expect($queue->status)->equals(SendingQueue::STATUS_COMPLETED);
  }

  function testItReschedulesCheckOnException() {
    $this->sskeycheck->bridge = Stub::make(
      new Bridge,
      array('checkKey' => function () { throw new \Exception(); }),
      $this
    );
    $this->setMailPoetSendingMethod();
    $queue = $this->createRunningQueue();
    $scheduled_at = $queue->scheduled_at;
    $this->sskeycheck->prepareQueue($queue);
    $this->sskeycheck->processQueue($queue);
    expect($scheduled_at < $queue->scheduled_at)->true();
  }

  function testItReschedulesCheckOnError() {
    $this->sskeycheck->bridge = Stub::make(
      new Bridge,
      array('checkKey' => array('code' => 503)),
      $this
    );
    $this->setMailPoetSendingMethod();
    $queue = $this->createRunningQueue();
    $scheduled_at = $queue->scheduled_at;
    $this->sskeycheck->prepareQueue($queue);
    $this->sskeycheck->processQueue($queue);
    expect($scheduled_at < $queue->scheduled_at)->true();
  }

  function testItCalculatesNextRunDateWithinNextWeekBoundaries() {
    $current_date = Carbon::createFromTimestamp(current_time('timestamp'));
    $next_run_date = SSKeyCheck::getNextRunDate();
    $difference = $next_run_date->diffInDays($current_date);
    // Subtract days left in the current week
    $difference -= (Carbon::DAYS_PER_WEEK - $current_date->format('N'));
    expect($difference)->lessOrEquals(7);
    expect($difference)->greaterOrEquals(0);
  }

  private function setMailPoetSendingMethod() {
    Setting::setValue(
      Mailer::MAILER_CONFIG_SETTING_NAME,
      array(
        'method' => 'MailPoet',
        'mailpoet_api_key' => 'some_key',
      )
    );
  }

  private function createScheduledQueue() {
    $queue = SendingQueue::create();
    $queue->type = SSKeyCheck::TASK_TYPE;
    $queue->status = SendingQueue::STATUS_SCHEDULED;
    $queue->scheduled_at = Carbon::createFromTimestamp(current_time('timestamp'));
    $queue->newsletter_id = 0;
    $queue->save();
    return $queue;
  }

  private function createRunningQueue() {
    $queue = SendingQueue::create();
    $queue->type = SSKeyCheck::TASK_TYPE;
    $queue->status = null;
    $queue->scheduled_at = Carbon::createFromTimestamp(current_time('timestamp'));
    $queue->newsletter_id = 0;
    $queue->save();
    return $queue;
  }

  function _after() {
    ORM::raw_execute('TRUNCATE ' . Setting::$_table);
    ORM::raw_execute('TRUNCATE ' . SendingQueue::$_table);
  }
}