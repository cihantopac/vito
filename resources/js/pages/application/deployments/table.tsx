import { useTranslate } from '@/hooks/use-translate';
import { Link, router } from '@inertiajs/react';
import type { CellRenderProps, InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { RotateCcwIcon } from 'lucide-react';
import { TableActionTrigger } from '@/components/table-action-trigger';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { VitoTable } from '@/components/vito-table';
import { useDialog } from '@/hooks/use-dialog';
import { asRow } from '@/lib/inertia-table';
import { Download, View } from '@/pages/server-logs/components/columns';
import { Deployment } from '@/types/deployment';
import DateTime from '@/components/date-time';
import { cn } from '@/lib/utils';
import CommitSummary from '@/pages/application/components/commit-summary';
import { DEPLOYMENT_STATES, DeploymentStateGlyph, deploymentActor, deploymentState } from '@/pages/application/components/deployment-state';
import { formatDuration } from '@/pages/application/deployments/progress';

const deploymentUrl = (deployment: Deployment) =>
  `/servers/${encodeURIComponent(String(deployment.server_id))}/sites/${deployment.site_id}/deployments/${deployment.id}`;

const commitCell = ({ row }: CellRenderProps, translateText: ReturnType<typeof useTranslate>) => {
  const deployment = row as unknown as Deployment;
  const state = deploymentState(deployment);
  const actor = deploymentActor(deployment);
  const duration = state !== 'deploying' && deployment.duration != null ? formatDuration(deployment.duration) : null;
  const details = [duration, actor].filter(Boolean);

  return (
    <div className="flex min-w-0 items-start gap-3">
      <div className="flex h-5 items-center">
        <DeploymentStateGlyph state={state} />
      </div>
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <CommitSummary commit={deployment.commit_data} showScope={false} className="text-sm" />
        <div className="text-muted-foreground flex min-w-0 items-center gap-1.5 text-[13px]">
          <span className={cn('shrink-0 font-medium', DEPLOYMENT_STATES[state].className)}>{translateText(DEPLOYMENT_STATES[state].label)}</span>
          {details.map((detail) => (
            <span key={detail} className="truncate">
              · {detail}
            </span>
          ))}
          {deployment.is_duplicate && (
            <span className="text-warning inline-flex shrink-0 items-center gap-1" title={translateText("This commit was already deployed previously")}>
              · <RotateCcwIcon className="size-3.5" />
              {translateText('Redeploy')}
            </span>
          )}
        </div>
      </div>
    </div>
  );
};

const deployedAtCell = ({ row }: CellRenderProps) => {
  const deployment = row as unknown as Deployment;

  return (
    <DateTime date={deployment.created_at} relative className="text-muted-foreground text-[13px] whitespace-nowrap" />
  );
};

function DeploymentRowActions({ deployment }: { deployment: Deployment }) {
  const translateText = useTranslate();
  const dialog = useDialog();

  return (
    <div className="flex items-center gap-2">
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <TableActionTrigger variant="ghost" className="shadow-none" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
          <DropdownMenuItem asChild>
            <Link href={deploymentUrl(deployment)}>
              {translateText('View details')}
            </Link>
          </DropdownMenuItem>
          {deployment.log && (
            <>
              <View serverLog={deployment.log} />
              <Download serverLog={deployment.log}>
                <DropdownMenuItem>{translateText('Download')}</DropdownMenuItem>
              </Download>
            </>
          )}
          <DropdownMenuItem
            variant="destructive"
            onSelect={() =>
              dialog.confirm.open({
                title: translateText("Delete release [:release]", { release: deployment.release || deployment.id }),
                description: translateText("Are you sure you want to delete release :release? This will delete the release files from the server. This action cannot be undone.", { release: deployment.release || deployment.id }),
                variant: 'destructive',
                confirmLabel: translateText('Delete'),
                method: 'delete',
                url: deploymentUrl(deployment),
              })
            }
          >
            {translateText('Delete')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

export default function DeploymentsTable({
  deployments,
  showPagination = true,
  modal = false,
}: {
  deployments: InertiaTableData;
  showPagination?: boolean;
  modal?: boolean;
}) {
  const translateText = useTranslate();
  if (!deployments?.columns) {
    return null;
  }

  return (
    <VitoTable
      modal={modal}
      columnWidths={{ actions: '3.25rem', created_at: '8rem' }}
      tableData={deployments}
      showPagination={showPagination}
      reloadOnly={['deployments']}
      onRowClick={(row: Row) => router.visit(deploymentUrl(row as unknown as Deployment))}
      cellRenderers={{
        commit: (props) => commitCell(props, translateText),
        created_at: deployedAtCell,
      }}
      actions={(row: Row) => <DeploymentRowActions deployment={asRow<Deployment>(row, ['id', 'site_id', 'server_id'])} />}
    />
  );
}
