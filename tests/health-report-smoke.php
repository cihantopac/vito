<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'app.url' => 'https://vito.test', 'mail.default' => 'log']);
$app['db']->purge('sqlite');
Illuminate\Support\Facades\URL::forceRootUrl('https://vito.test');
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));
foreach (['users','projects','user_project','servers','services','workers','sites','site_resources','metrics'] as $name) {
 Schema::create($name, function ($t) use ($name) {
  $t->id();$t->timestamps();
  if ($name==='users') {$t->string('name');$t->string('email');}
  if ($name==='projects') {$t->string('name');$t->softDeletes();}
  if ($name==='user_project') {$t->integer('project_id');$t->integer('user_id');$t->string('role');}
  if ($name==='servers') {$t->integer('project_id');$t->string('name');$t->string('status');$t->integer('updates');$t->integer('kernel_updates');}
  if (in_array($name,['services','workers','sites','site_resources','metrics'])) {$t->integer('server_id');}
  if (in_array($name,['services','workers','sites','site_resources'])) {$t->string('status');}
  if (in_array($name,['services','workers'])) {$t->string('name');}
  if (in_array($name,['workers','site_resources'])) {$t->integer('site_id')->nullable();}
  if ($name==='sites') {$t->string('domain');}
  if ($name==='site_resources') {$t->string('type');}
  if ($name==='metrics') {foreach (['memory_total','memory_used','disk_total','disk_used'] as $col) {$t->float($col);}}
 });
}
$migration=require glob(dirname(__DIR__).'/database/migrations/*create_health_report_tables.php')[0];$migration->up();
function insert(string $model,array $data): void {$model::query()->insert(array_merge($data,['created_at'=>now(),'updated_at'=>now()]));}
insert(App\Models\User::class,['id'=>1,'name'=>'Admin','email'=>'admin@example.test']);
insert(App\Models\User::class,['id'=>2,'name'=>'Reader','email'=>'reader@example.test']);
foreach ([1,2,3] as $id) {insert(App\Models\Project::class,['id'=>$id,'name'=>'Project '.$id]);}
foreach ([1,2] as $id) {insert(App\Models\UserProject::class,['user_id'=>1,'project_id'=>$id,'role'=>'admin']);}
insert(App\Models\UserProject::class,['user_id'=>1,'project_id'=>3,'role'=>'user']);
insert(App\Models\UserProject::class,['user_id'=>2,'project_id'=>1,'role'=>'user']);
insert(App\Models\Server::class,['id'=>1,'project_id'=>1,'name'=>'Server','status'=>'ready','updates'=>2,'kernel_updates'=>0]);
insert(App\Models\Service::class,['server_id'=>1,'name'=>'nginx','status'=>'stopped']);
insert(App\Models\Site::class,['id'=>1,'server_id'=>1,'domain'=>'site.test','status'=>'ready']);
insert(App\Models\Worker::class,['server_id'=>1,'site_id'=>1,'name'=>'queue','status'=>'failed']);
insert(App\Models\SiteResource::class,['server_id'=>1,'site_id'=>1,'type'=>'database','status'=>'failed']);
insert(App\Models\Metric::class,['server_id'=>1,'memory_total'=>100,'memory_used'=>91,'disk_total'=>100,'disk_used'=>90]);
app(App\Actions\Reporting\CollectHealthReport::class)->handle();
app(App\Actions\Reporting\CollectHealthReport::class)->handle();
if (App\Models\HealthReportSnapshot::query()->count()!==3) {throw new Exception('Hourly duplicates');}
$issues=App\Models\HealthReportSnapshot::query()->where('project_id',1)->first()->issues;
if (count($issues)!==6) {throw new Exception('Issue/threshold coverage: '.json_encode($issues));}
Mail::fake();
$action=app(App\Actions\Reporting\SendDailyHealthReports::class);
$action->handle(1,'2026-11-02');$action->handle(1,'2026-11-02');$action->handle(2,'2026-11-02');
if (Mail::sent(App\Mail\DailyHealthReport::class)->count() !== 1) {throw new Exception('Duplicate email');}
if (Mail::sent(App\Mail\DailyHealthReport::class,fn ($mail)=>$mail->report['projects']===2 && $mail->hasTo('admin@example.test'))->count() !== 1) {throw new Exception('Permission or grouping failure');}
$mail=new App\Mail\DailyHealthReport(app(App\Actions\Reporting\SendDailyHealthReports::class)->report(App\Models\User::query()->find(1),'2026-11-02'),'2026-11-02');
$html=$mail->render();
if (!str_contains($html,'1 servers critical') || !str_contains($html,'https://vito.test/servers/1/update') || str_contains($html,'affected by server')) {throw new Exception('Email links/render: '.$html);}

echo "PASS: hourly idempotency, >90% boundary, service/worker/resource issues, affected sites, project authorization, per-user daily deduplication, email rendering and links.\n";

Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));
$action->handle(1, '2026-11-02');
if (App\Models\HealthReportDelivery::query()->count() !== 1) {throw new Exception('Delayed job sent an old report');}
Mail::swap(new class {
    public function to(string $email): self {return $this;}
    public function send($mail): void {throw new RuntimeException('Simulated transport failure');}
});
try {$action->handle(1, '2026-11-03');} catch (RuntimeException $e) {}
$action->handle(1, '2026-11-03');
if (App\Models\HealthReportDelivery::query()->where('report_date','2026-11-03')->value('status') !== 'failed') {throw new Exception('Failed send must not be retried');}
echo "PASS: delayed jobs and failed sends cannot produce another daily email.\n";
