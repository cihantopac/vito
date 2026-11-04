import { Link } from '@inertiajs/react';
import type { CellRenderProps, InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { Badge } from '@/components/ui/badge';
import { LoaderCircleIcon } from 'lucide-react';
import { TableActionTrigger } from '@/components/table-action-trigger';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { VitoTable } from '@/components/vito-table';
import { useDialog } from '@/hooks/use-dialog';
import { asRow } from '@/lib/inertia-table';
import { Download, View } from '@/pages/server-logs/components/columns';
import { Deployment } from '@/types/deployment';
import DateTime from '@/components/date-time';
import moment from 'moment';

const commitCell = ({ row }: CellRenderProps) => {
  const commit = (row.commit_data ?? {}) as Deployment['commit_data'];
  const isDuplicate = Boolean(row.is_duplicate);
  const commitShort = (row.commit_id_short as string) || (row.commit_id ? String(row.commit_id).slice(0, 7) : '');
  const originalDeploymentId = row.original_deployment_id as number | undefined;

  if (!commit.message && !commitShort) {
    return <span className="text-muted-foreground">—</span>;
  }

  const href = commit.url && /^https?:\/\//.test(commit.url) ? commit.url : undefined;
  const duplicateTitle = originalDeploymentId
    ? `Same commit already deployed in deployment #${originalDeploymentId}`
    : 'Same commit already deployed in an earlier deployment';

  return (
    <div className="flex flex-col gap-0.5 min-w-0">
      <div className="flex items-center gap-1.5 min-w-0">
        {href ? (
          <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="text-foreground hover:text-info whitespace-normal break-words font-medium text-xs hover:underline"
            title={commit.message || commitShort}
          >
            {commit.message || commitShort}
          </a>
        ) : (
          <span
            className="text-foreground whitespace-normal break-words font-medium text-xs"
            title={commit.message || commitShort}
          >
            {commit.message || commitShort}
          </span>
        )}

        {isDuplicate && (
          <Badge
            variant="outline"
            className="text-[10px] uppercase font-mono px-1 py-0 shrink-0 text-neutral-500 border-neutral-500/30 bg-neutral-500/10 dark:text-neutral-400"
            title={duplicateTitle}
          >
            duplicate
          </Badge>
        )}
      </div>

      <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground font-mono flex-wrap">
        {commitShort && (
          href ? (
            <a
              href={href}
              target="_blank"
              rel="noopener noreferrer"
              className="text-muted-foreground hover:text-foreground underline decoration-dotted"
              title="View commit"
            >
              {commitShort}
            </a>
          ) : (
            <span>{commitShort}</span>
          )
        )}

        {commit.date && (
          <>
            <span className="text-muted-foreground/50">·</span>
            <span title={`Committed on ${moment(commit.date).format('YYYY-MM-DD HH:mm:ss')}`}>
              Committed <DateTime date={commit.date} relative />
            </span>
          </>
        )}

        {commit.name && (
          <>
            <span className="text-muted-foreground/50">·</span>
            <span className="truncate max-w-[120px]" title={commit.name}>
              {commit.name}
            </span>
          </>
        )}
      </div>
    </div>
  );
};

const releaseCell = ({ row }: CellRenderProps) => (
  <div className="inline-flex items-center gap-2">
    {(row.release as string | null) ?? ''}
    {(row.active as boolean) && <Badge variant="default">active</Badge>}
  </div>
);

const deployedAtCell = ({ row, value }: CellRenderProps) => {
  const dateStr = String(row.created_at || value || '');
  if (!dateStr) return <span className="text-muted-foreground">—</span>;

  return (
    <div className="flex flex-col text-xs cursor-default">
      <span className="font-medium text-foreground">
        <DateTime date={dateStr} relative />
      </span>
      <span className="text-[11px] text-muted-foreground" title={dateStr}>
        {moment(dateStr).format('MMM D, HH:mm')}
      </span>
    </div>
  );
};

const deployedByCell = ({ row }: CellRenderProps) => {
  const deployment = row as unknown as Deployment;

  return (
    <div className="flex flex-col gap-1">
      <div className="inline-flex items-center gap-2">
        <span>{deployment.deployed_by ?? (deployment.trigger ? 'System' : '—')}</span>
        {deployment.trigger && deployment.trigger_color && <Badge variant={deployment.trigger_color}>{deployment.trigger}</Badge>}
      </div>
      {deployment.rolled_back_at && (
        <span className="text-xs text-muted-foreground">
          Rolled back by {deployment.rolled_back_by ?? 'System'} <DateTime date={deployment.rolled_back_at} relative />
        </span>
      )}
    </div>
  );
};

const statusCell = ({ row, value }: CellRenderProps) => {
  const status = String(row.status || value || '');
  const statusColor = (row.status_color as 'warning' | 'success' | 'danger' | 'gray') || 'default';

  return (
    <div className="inline-flex items-center gap-1.5">
      {status === 'deploying' && <LoaderCircleIcon className="size-3.5 animate-spin text-warning shrink-0" />}
      <Badge variant={statusColor}>{status}</Badge>
    </div>
  );
};

const durationCell = ({ row }: CellRenderProps) => {
  const deployment = row as unknown as Deployment;
  if (deployment.status === 'deploying') {
    return (
      <span className="text-muted-foreground text-xs font-mono">Running</span>
    );
  }

  if (deployment.formatted_duration) {
    return <span className="text-xs font-mono text-muted-foreground font-medium">{deployment.formatted_duration}</span>;
  }

  if (deployment.finished_at && deployment.created_at) {
    const diff = Math.max(0, moment(deployment.finished_at).diff(moment(deployment.created_at), 'seconds'));
    const minutes = Math.floor(diff / 60);
    const text = minutes ? `${minutes}m ${String(diff % 60).padStart(2, '0')}s` : `${diff}s`;

    return <span className="text-xs font-mono text-muted-foreground font-medium">{text}</span>;
  }

  return <span className="text-muted-foreground">—</span>;
};

export default function DeploymentsTable({ deployments, showPagination = true }: { deployments: InertiaTableData; showPagination?: boolean }) {
  const dialog = useDialog();

  if (!deployments || !deployments.columns) {
    return null;
  }

  return (
    <VitoTable
      fitContent
      columnWidths={{ actions: '3.25rem', id: '4.5rem', deployed_by: '12%', status: '10%', duration: '8%', release: '9%', created_at: '15%' }}
      tableData={deployments}
      showPagination={showPagination}
      reloadOnly={['deployments']}
      cellRenderers={{
        commit: commitCell,
        release: releaseCell,
        created_at: deployedAtCell,
        deployed_by: deployedByCell,
        status: statusCell,
        duration: durationCell,
      }}
      actions={(row: Row) => {
        const deployment = asRow<Deployment>(row, ['id', 'site_id', 'server_id']);

        return (
          <div className="flex items-center gap-2">
            <DropdownMenu modal={false}>
              <DropdownMenuTrigger asChild>
                <TableActionTrigger />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start">
                <DropdownMenuItem asChild>
                  <Link
                    href={`/servers/${encodeURIComponent(String(deployment.server_id))}/sites/${deployment.site_id}/deployments/${deployment.id}`}
                  >
                    View details
                  </Link>
                </DropdownMenuItem>
                {deployment.log && (
                  <>
                    <View serverLog={deployment.log} />
                    <Download serverLog={deployment.log}>
                      <DropdownMenuItem>Download</DropdownMenuItem>
                    </Download>
                  </>
                )}
                {!deployment.active && deployment.release && deployment.status === 'finished' && (
                  <DropdownMenuItem
                    variant="destructive"
                    onSelect={() =>
                      dialog.confirm.open({
                        title: 'Rollback',
                        description: `Are you sure you want to rollback your site to version [${deployment.release}]?`,
                        confirmLabel: 'Rollback',
                        method: 'post',
                        url: `/servers/${encodeURIComponent(String(deployment.server_id))}/sites/${deployment.site_id}/rollback/${deployment.id}`,
                      })
                    }
                  >
                    Rollback
                  </DropdownMenuItem>
                )}
                <DropdownMenuItem
                  variant="destructive"
                  onSelect={() =>
                    dialog.confirm.open({
                      title: `Delete release [${deployment.release || deployment.id}]`,
                      description: `Are you sure you want to delete release ${deployment.release || deployment.id}? This will delete the release files from the server. This action cannot be undone.`,
                      variant: 'destructive',
                      confirmLabel: 'Delete',
                      method: 'delete',
                      url: `/servers/${encodeURIComponent(String(deployment.server_id))}/sites/${deployment.site_id}/deployments/${deployment.id}`,
                    })
                  }
                >
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        );
      }}
    />
  );
}
