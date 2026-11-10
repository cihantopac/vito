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

const commitCell = ({ row }: CellRenderProps) => {
  const commit = (row.commit_data ?? {}) as Deployment['commit_data'];
  if (!commit?.message) {
    return <span className="text-muted-foreground">No message</span>;
  }

  const content = <span className="block whitespace-normal break-words">{commit.message}</span>;
  const href = commit.url && /^https?:\/\//.test(commit.url) ? commit.url : undefined;

  return href ? (
    <a href={href} target="_blank" rel="noopener noreferrer" className="text-foreground hover:text-info inline-flex text-xs font-medium hover:underline">
      {content}
    </a>
  ) : (
    <span className="inline-flex text-xs font-medium">{content}</span>
  );
};

const deployedAtCell = ({ row, value }: CellRenderProps) => {
  const dateStr = String(row.created_at || value || '');
  if (!dateStr) return <span className="text-muted-foreground">—</span>;

  return (
    <span className="text-xs font-medium text-foreground cursor-default">
      <DateTime date={dateStr} relative />
    </span>
  );
};

const deployedByCell = ({ row }: CellRenderProps) => {
  const deployment = row as unknown as Deployment;

  return (
    <div className="inline-flex items-center gap-2">
      <span>{deployment.deployed_by ?? (deployment.trigger ? 'System' : '—')}</span>
      {deployment.trigger && deployment.trigger_color && <Badge variant={deployment.trigger_color}>{deployment.trigger}</Badge>}
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

const formatSeconds = (seconds: number): string => {
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;

  return minutes > 0 ? `${minutes}m ${String(remainingSeconds).padStart(2, '0')}s` : `${seconds}s`;
};

const durationCell = ({ row, value }: CellRenderProps) => {
  const deployment = row as unknown as Deployment;
  if (deployment.status === 'deploying') {
    return <span className="text-warning text-xs font-mono">Running</span>;
  }

  const formatted = (value as string) || deployment.formatted_duration;
  if (formatted) {
    return <span className="text-xs font-mono text-muted-foreground font-medium">{formatted}</span>;
  }

  if (deployment.finished_at && deployment.created_at) {
    const diff = Math.max(0, Math.floor((new Date(deployment.finished_at).getTime() - new Date(deployment.created_at).getTime()) / 1000));

    return <span className="text-xs font-mono text-muted-foreground font-medium">{formatSeconds(diff)}</span>;
  }

  return <span className="text-muted-foreground">—</span>;
};

function DeploymentRowActions({ deployment }: { deployment: Deployment }) {
  const dialog = useDialog();

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
}

export default function DeploymentsTable({ deployments, showPagination = true }: { deployments: InertiaTableData; showPagination?: boolean }) {
  if (!deployments?.columns) {
    return null;
  }

  return (
    <VitoTable
      fitContent
      columnWidths={{ actions: '3.25rem', id: '4.5rem', deployed_by: '12%', status: '10%', duration: '8%', created_at: '15%' }}
      tableData={deployments}
      showPagination={showPagination}
      reloadOnly={['deployments']}
      cellRenderers={{
        commit: commitCell,
        created_at: deployedAtCell,
        deployed_by: deployedByCell,
        status: statusCell,
        duration: durationCell,
      }}
      actions={(row: Row) => <DeploymentRowActions deployment={asRow<Deployment>(row, ['id', 'site_id', 'server_id'])} />}
    />
  );
}
