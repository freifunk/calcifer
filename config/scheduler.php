// config/scheduler.php
   use Symfony\Component\Scheduler\Schedule;
   use Symfony\Component\Scheduler\RecurringMessage;
   
   return function (Schedule $schedule) {
       $schedule->add('events_generation', '0 3 * * *')
           ->command('app:events:generate', ['--duration' => '3 months']);
   };