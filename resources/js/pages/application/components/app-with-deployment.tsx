import { useTranslate } from '@/hooks/use-translate';
import { useCallback, useEffect, useState } from 'react';
import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { Site } from '@/types/site';
import ServerLayout from '@/layouts/server/layout';
import { Server } from '@/types/server';
import Container from '@/components/container';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CalendarClockIcon, ChevronDownIcon, ChevronUpIcon, CodeXmlIcon, ExternalLinkIcon, FileCode2Icon, GitBranchIcon, Globe2Icon, ListEndIcon, LockKeyholeIcon, MoreHorizontalIcon, NetworkIcon, ServerIcon, UserRoundIcon, XIcon, ZapIcon, type LucideIcon } from 'lucide-react';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import DeploymentScript from '@/pages/application/components/deployment-script';
import Deploy from '@/pages/application/components/deploy';
import AutoDeployment from '@/pages/application/components/auto-deployment';
import { DeploymentScript as DeploymentScriptType } from '@/types/deployment-script';
import { useRealtimeRecord, useSocketListener } from '@/hooks/use-socket-events';
import type { InertiaTableData } from '@forjedio/inertia-table-react';

import SiteBanners from '@/components/site-banners';
import ProxiedAppCard from '@/pages/application/components/proxied-app-card';
import { Worker } from '@/types/worker';
import { CronJob } from '@/types/cronjob';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import DeploymentsTable from '@/pages/application/deployments/table';
import DeploymentProgress from '@/pages/application/deployments/progress';
import { useLogContent } from '@/hooks/use-log-content';
import LogOutput from '@/components/log-output';
import { Deployment } from '@/types/deployment';
import { DeploymentsSkeleton, WorkersCronJobsSkeleton } from '@/components/page-skeleton';
import DateTime from '@/components/date-time';
import { usePanelView } from '@/hooks/use-panel-view';

function Detail({ icon: Icon, label, children }: { icon: LucideIcon; label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-center gap-3">
      <div className="text-muted-foreground flex size-5 shrink-0 items-center justify-center">
        <Icon className="size-4" />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-muted-foreground text-xs">{label}</p>
        <p className="break-words text-sm font-medium">{children}</p>
      </div>
    </div>
  );
}

function StatusDetail({ icon: Icon, label, enabled }: { icon: LucideIcon; label: string; enabled: boolean }) {
  const translateText = useTranslate();
  return (
    <div className="flex min-w-0 flex-col gap-2 p-4">
      <div className="text-muted-foreground flex items-center gap-2 text-xs">
        <Icon className="size-3.5" />
        {label}
      </div>
      <Badge variant={enabled ? 'success' : 'outline'}>{enabled ? translateText("Enabled") : translateText("Disabled")}</Badge>
    </div>
  );
}

function formatSiteType(type?: string): string {
  if (!type) return '';
  return type
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export default function AppWithDeployment() {
  const translateText = useTranslate();
  const page = usePage<{
    server: Server;
    site: Site;
    deployments: InertiaTableData;
    activeDeployment?: Deployment | null;
    deploymentScript: DeploymentScriptType;
    buildScript?: DeploymentScriptType;
    preFlightScript?: DeploymentScriptType;
    worker: Worker | null;
    overviewWorkers: Worker[];
    overviewWorkersCount: number;
    overviewCronJobs: CronJob[];
    overviewCronJobsCount: number;
  }>();
  const site = useRealtimeRecord<Site>(page.props.site, 'site')!;
  const { advanced, setView } = usePanelView();
  const [showDetails, setShowDetails] = useState(false);
  const latestDeployment = page.props.deployments?.data[0] as unknown as Deployment | undefined;
  const [activeDeployment, setActiveDeployment] = useState<Deployment | null>(page.props.activeDeployment ?? null);
  const [showLog, setShowLog] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    if (page.props.activeDeployment) {
      setActiveDeployment(page.props.activeDeployment);
      setDismissed(false);
    } else {
      setActiveDeployment((prev) => (prev && prev.site_id === site.id && prev.status !== 'deploying' ? prev : null));
    }
  }, [page.props.activeDeployment, site.id]);

  useSocketListener(
    useCallback(
      (event) => {
        if (event.type === 'deployment.created') {
          const created = event.data as Deployment;
          if (created.site_id === site.id) {
            setActiveDeployment(created);
            setDismissed(false);
          }
        } else if (event.type === 'deployment.updated') {
          const updated = event.data as Deployment;
          if (updated.site_id === site.id) {
            setActiveDeployment((prev) => (prev && prev.id === updated.id ? updated : prev ?? (updated.status === 'deploying' ? updated : null)));
          }
        }
      },
      [site.id],
    ),
  );

  const { content, step, isLoading } = useLogContent({
    serverId: page.props.server.id,
    logId: activeDeployment?.log?.id ?? activeDeployment?.log_id ?? 0,
    enabled: !!activeDeployment && !dismissed,
  });

  return (
    <ServerLayout>
      <Head title={`${site.domain} - ${page.props.server.name}`} />

      <Container className="gap-4">
        {site.status === 'installation_failed' && <SiteBanners site={site} includeAppDebug />}

        <div className="flex flex-wrap items-center justify-between gap-2">
          <h1 className="text-lg font-semibold">{site.domain}</h1>
          <div className="bg-muted/40 flex gap-1 rounded-md border p-1" role="group" aria-label={translateText('View')}>
            <Button size="sm" variant={advanced ? 'ghost' : 'secondary'} aria-pressed={!advanced} onClick={() => setView('basic')}>{translateText('Basic')}</Button>
            <Button size="sm" variant={advanced ? 'secondary' : 'ghost'} aria-pressed={advanced} onClick={() => setView('advanced')}>{translateText('Advanced')}</Button>
          </div>
        </div>

        <div className={advanced ? 'grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]' : 'grid gap-3'}>
          <div className="flex min-w-0 flex-col gap-4">
            {site.is_proxied_site_type && (
              <ProxiedAppCard site={site} initialWorker={page.props.worker} />
            )}

            <Deferred data={['deployments', 'deploymentScript']} fallback={<DeploymentsSkeleton />}>
              <section aria-labelledby="deployments-heading">
                <Card>
                  <CardHeader className="gap-3 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                      <div className="flex min-w-0 items-center gap-3">
                        <div className="min-w-0">
                          <div className="flex min-w-0 items-center gap-2">
                            <CardTitle id="deployments-heading">{translateText('Deployments')}</CardTitle>
                            <Badge variant={site.status_color}>{site.status}</Badge>
                          </div>
                          <div className="text-muted-foreground mt-1 flex min-w-0 items-center gap-2 text-sm">
                            {site.repository ? (
                              <>
                                <GitBranchIcon className="size-4 shrink-0" />
                                <span className="truncate">{site.repository}</span>
                                {site.branch && <span className="shrink-0">· {site.branch}</span>}
                              </>
                            ) : (
                              <span>{formatSiteType(site.type)} site</span>
                            )}
                          </div>
                        </div>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        {site.status !== 'installation_failed' && <SiteBanners site={site} compact includeAppDebug />}
                        <Deploy site={site} />
                        <Button variant="outline" asChild className="gap-1.5 cursor-pointer">
                          <Link href={`/servers/${page.props.server.id}/sites/${site.id}/environment`}>
                            <FileCode2Icon className="size-4" />
                            <span>Env</span>
                          </Link>
                        </Button>
                        <DropdownMenu modal={false}>
                          <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="p-0">
                              <span className="sr-only">{translateText('Open menu')}</span>
                              <MoreHorizontalIcon />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <AutoDeployment site={site}>
                              <DropdownMenuItem onSelect={(e) => e.preventDefault()} disabled={!site.source_control_id}>
                                {site.auto_deploy ? translateText("Disable") : translateText("Enable")} {translateText("auto deploy")}
                              </DropdownMenuItem>
                            </AutoDeployment>
                            {!site.modern_deployment && (
                              <DeploymentScript site={site} script={page.props.deploymentScript} description={translateText("This script will be executed on every deployment.")}>
                                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>{translateText("Deployment Script")}</DropdownMenuItem>
                              </DeploymentScript>
                            )}
                            {page.props.buildScript && site.modern_deployment && (
                              <DeploymentScript
                                site={site}
                                script={page.props.buildScript}
                                description={translateText("This script will build resources like composer and npm before release")}
                              >
                                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>{translateText("Build Script")}</DropdownMenuItem>
                              </DeploymentScript>
                            )}
                            {page.props.preFlightScript && site.modern_deployment && (
                              <DeploymentScript
                                site={site}
                                script={page.props.preFlightScript}
                                description={translateText("This script will be executed before release like migrations and optimizations")}
                              >
                                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>{translateText("Pre Flight Script")}</DropdownMenuItem>
                              </DeploymentScript>
                            )}
                            <DropdownMenuItem asChild>
                              <Link href={`/servers/${page.props.server.id}/sites/${site.id}/environment`}>
                                {translateText("Environment (.env)")}
                              </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                              <Link href={`/servers/${page.props.server.id}/sites/${site.id}/logs`}>
                                {translateText('Logs')}
                              </Link>
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </div>
                  </CardHeader>
                  {activeDeployment && !dismissed && (
                    <div className="border-t p-4 flex flex-col gap-3">
                      <DeploymentProgress
                        deployment={activeDeployment}
                        step={step}
                        action={
                          <div className="flex items-center gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              className="h-8 gap-1.5 text-xs cursor-pointer"
                              onClick={() => setShowLog((prev) => !prev)}
                            >
                              {showLog ? <ChevronUpIcon className="size-3.5" /> : <ChevronDownIcon className="size-3.5" />}
                              <span>{showLog ? translateText("Hide log") : translateText("Show log")}</span>
                            </Button>

                            <Button variant="outline" size="sm" asChild className="h-8 gap-1.5 text-xs cursor-pointer">
                              <Link href={`/servers/${page.props.server.id}/sites/${site.id}/deployments/${activeDeployment.id}`}>
                                <span>{translateText('Details')}</span>
                                <ExternalLinkIcon className="size-3.5" />
                              </Link>
                            </Button>

                            {activeDeployment.status !== 'deploying' && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 cursor-pointer"
                                onClick={() => setDismissed(true)}
                                title={translateText('Dismiss')}
                              >
                                <XIcon className="size-3.5" />
                              </Button>
                            )}
                          </div>
                        }
                      />

                      {showLog && (
                        <Card className="overflow-hidden border">
                          <LogOutput className="h-64 w-full rounded-none border-0 p-4 font-mono text-xs">
                            {isLoading && translateText("Loading deployment output...")}
                            {!isLoading && (content || (activeDeployment.status === 'deploying' ? translateText("Waiting for output...") : translateText("No output was recorded.")))}
                          </LogOutput>
                        </Card>
                      )}
                    </div>
                  )}
                  <CardContent className="p-4">
                    {advanced ? (
                      <DeploymentsTable deployments={page.props.deployments} showPagination={false} />
                    ) : latestDeployment ? (
                      <Link
                        href={`/servers/${site.server_id}/sites/${site.id}/deployments/${latestDeployment.id}`}
                        className="hover:bg-muted/30 flex flex-wrap items-center justify-between gap-3 rounded-md p-2 text-sm"
                      >
                        <div className="min-w-0 flex-1">
                          <p className="text-muted-foreground text-xs">{translateText("Latest deployment · #")}{latestDeployment.id}</p>
                          <p className="break-words font-medium">{latestDeployment.commit_data?.message || latestDeployment.commit_id_short || translateText("Deployment")}</p>
                        </div>
                        <Badge variant={latestDeployment.status_color}>{latestDeployment.status}</Badge>
                        <DateTime date={latestDeployment.created_at} relative className="text-muted-foreground text-xs" />
                      </Link>
                    ) : (
                      <p className="text-muted-foreground text-sm">{translateText("No deployments yet.")}</p>
                    )}
                  </CardContent>
                  <CardFooter className="justify-end p-3">
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/servers/${site.server_id}/sites/${site.id}/deployments`}>{translateText("View all deployments")}</Link>
                    </Button>
                  </CardFooter>
                </Card>
              </section>
            </Deferred>

            {!advanced && (
              <div className="flex flex-wrap gap-2" aria-label={translateText("Site shortcuts")}>
                {[
                  ['workers', 'Workers'],
                  ['cronjobs', 'Cron jobs'],
                  ['logs', 'Logs'],
                  ['resources', 'Resources'],
                ].map(([path, label]) => (
                  <Button key={path} variant="outline" size="sm" asChild>
                    <Link href={`/servers/${site.server_id}/sites/${site.id}/${path}`}>{label}</Link>
                  </Button>
                ))}
              </div>
            )}

            {advanced && <Deferred data={['overviewWorkers', 'overviewCronJobs']} fallback={<WorkersCronJobsSkeleton />}>
              <section className="grid gap-4 md:grid-cols-2" aria-label={translateText("Site automation")}>
                <Card>
                  <CardHeader className="flex-row items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                      <div className="bg-muted text-muted-foreground flex size-8 items-center justify-center rounded-lg">
                        <ListEndIcon className="size-4" />
                      </div>
                      <div>
                        <CardTitle>{translateText("Background processes")}</CardTitle>
                        <p className="text-muted-foreground mt-1 text-xs">{page.props.overviewWorkersCount ?? 0} {translateText('configured')}</p>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/servers/${site.server_id}/sites/${site.id}/workers`}>{translateText('Manage')}</Link>
                    </Button>
                  </CardHeader>
                  <CardContent className="divide-y p-0">
                    {page.props.overviewWorkers && page.props.overviewWorkers.length > 0 ? (
                      page.props.overviewWorkers?.map((worker) => (
                        <div key={worker.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                          <div className="min-w-0">
                            <p className="truncate font-medium">{worker.name || worker.command}</p>
                            <p className="text-muted-foreground truncate">{worker.command}</p>
                          </div>
                          <Badge variant={worker.status_color}>{worker.status}</Badge>
                        </div>
                      ))
                    ) : (
                      <p className="text-muted-foreground px-4 py-6 text-center text-sm">{translateText("No background processes yet.")}</p>
                    )}
                    {(page.props.overviewWorkersCount ?? 0) > (page.props.overviewWorkers?.length ?? 0) && (
                      <p className="text-muted-foreground px-4 py-2 text-xs">{translateText('Showing 3 of :total', { total: page.props.overviewWorkersCount })}</p>
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="flex-row items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                      <div className="bg-muted text-muted-foreground flex size-8 items-center justify-center rounded-lg">
                        <CalendarClockIcon className="size-4" />
                      </div>
                      <div>
                        <CardTitle>{translateText("Scheduled jobs")}</CardTitle>
                        <p className="text-muted-foreground mt-1 text-xs">{page.props.overviewCronJobsCount ?? 0} {translateText('configured')}</p>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/servers/${site.server_id}/sites/${site.id}/cronjobs`}>{translateText('Manage')}</Link>
                    </Button>
                  </CardHeader>
                  <CardContent className="divide-y p-0">
                    {page.props.overviewCronJobs && page.props.overviewCronJobs.length > 0 ? (
                      page.props.overviewCronJobs?.map((cronJob) => (
                        <div key={cronJob.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                          <div className="min-w-0">
                            <p className="truncate font-medium">{cronJob.name || cronJob.command}</p>
                            <p className="text-muted-foreground truncate">{cronJob.frequency}</p>
                          </div>
                          <Badge variant={cronJob.status_color}>{cronJob.status}</Badge>
                        </div>
                      ))
                    ) : (
                      <p className="text-muted-foreground px-4 py-6 text-center text-sm">{translateText("No scheduled jobs yet.")}</p>
                    )}
                    {(page.props.overviewCronJobsCount ?? 0) > (page.props.overviewCronJobs?.length ?? 0) && (
                      <p className="text-muted-foreground px-4 py-2 text-xs">{translateText('Showing 3 of :total', { total: page.props.overviewCronJobsCount })}</p>
                    )}
                  </CardContent>
                </Card>
              </section>
            </Deferred>}
          </div>

          {advanced && <Card className="overflow-hidden lg:sticky lg:top-4">
            <CardHeader className="flex-row items-center gap-3">
              <div className="flex flex-col gap-1">
                <CardTitle>{translateText('Site details')}</CardTitle>

              </div>
            </CardHeader>
            <CardContent className="p-0">
              <div className="flex flex-col gap-3 p-4">
                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{translateText('Connection')}</p>
                <Detail icon={ServerIcon} label={translateText('Server')}>
                  {page.props.server.name}
                </Detail>
                <Detail icon={NetworkIcon} label="Public IP">
                  <span className="font-mono">{page.props.server.ip}</span>
                </Detail>
              </div>

              <div className="flex flex-col gap-3 border-t p-4">
                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Runtime</p>
                <Detail icon={CodeXmlIcon} label={translateText("Site type")}>
                  {formatSiteType(site.type)}
                </Detail>
                {site.php_version && <Detail icon={CodeXmlIcon} label="PHP">{site.php_version}</Detail>}
              </div>

              <Collapsible open={showDetails} onOpenChange={setShowDetails}>
                <CollapsibleContent>
                  <div className="flex flex-col gap-3 border-t p-4">
                    <Detail icon={UserRoundIcon} label={translateText("Site user")}>
                      {site.user}
                    </Detail>
                    <Detail icon={Globe2Icon} label={translateText("Webserver")}>
                      {site.webserver}
                    </Detail>
                    {site.branch && <Detail icon={GitBranchIcon} label="Branch">{site.branch}</Detail>}
                  </div>
                  <div className="bg-muted/30 text-muted-foreground flex items-center justify-between gap-3 border-t px-4 py-3 text-xs">
                    <span>{translateText("Server ID:")} {page.props.server.id}</span>
                    <span>Site ID: {site.id}</span>
                  </div>
                </CollapsibleContent>
              </Collapsible>

              <div className="grid grid-cols-2 divide-x border-t">
                <StatusDetail icon={LockKeyholeIcon} label="SSL" enabled={site.ssl_enabled} />
                <StatusDetail icon={ZapIcon} label={translateText("Auto deploy")} enabled={site.auto_deploy} />
              </div>

              <div className="border-t p-2">
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-muted-foreground hover:text-foreground w-full justify-center text-xs"
                  onClick={() => setShowDetails(!showDetails)}
                >
                  {showDetails ? (
                    <>
                      {translateText("Hide details")}
                      <ChevronUpIcon className="ml-1.5 size-3.5" />
                    </>
                  ) : (
                    <>
                      {translateText("More details")}
                      <ChevronDownIcon className="ml-1.5 size-3.5" />
                    </>
                  )}
                </Button>
              </div>
            </CardContent>
          </Card>}
        </div>
      </Container>
    </ServerLayout>
  );
}
