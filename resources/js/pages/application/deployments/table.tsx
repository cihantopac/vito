import { useTranslate } from '@/hooks/use-translate';
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

const commitCell = ({ row }: CellRenderProps, translateText: ReturnType<typeof useTranslate>) => {
  const commit = (row.commit_data ?? {}) as Deployment['commit_data'];
  if (!commit?.message) {
    return <span className="text-muted-foreground">{translateText('No message')}</span>;
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

const deployedByCell = ({ row }: CellRenderProps, translateText: ReturnType<typeof useTranslate>) => {
  const deployment = row as unknown as Deployment;

  return (
    <div className="inline-flex items-center gap-2">
      <span>{deployment.deployed_by ?? (deployment.trigger ? translateText('System') : '—')}</span>
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

const durationCell = ({ row, value }: CellRenderProps, translateText: ReturnType<typeof useTranslate>) => {
  const deployment = row as unknown as Deployment;
  if (deployment.status === 'deploying') {
    return <span className="text-warning text-xs font-mono">{translateText('Running')}</span>;
  }

  const formatted = deployment.formatted_duration || (typeof value === 'string' ? value : null);
  if (formatted) {
    return <span className="text-xs font-mono text-muted-foreground font-medium">{formatted}</span>;
  }

  if (typeof value === 'number' && Number.isFinite(value) && value >= 0) {
    return <span className="text-xs font-mono text-muted-foreground font-medium">{formatSeconds(Math.floor(value))}</span>;
  }

  if (deployment.finished_at && deployment.created_at) {
    const diff = Math.floor((new Date(deployment.finished_at).getTime() - new Date(deployment.created_at).getTime()) / 1000);
    if (Number.isFinite(diff)) {
      return <span className="text-xs font-mono text-muted-foreground font-medium">{formatSeconds(Math.max(0, diff))}</span>;
    }
  }

  return <span className="text-muted-foreground">—</span>;
};

function DeploymentRowActions({ deployment }: { deployment: Deployment }) {
  const translateText = useTranslate();
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
              {translateText("View details")}
            </Link>
          </DropdownMenuItem>
          {deployment.log && (
            <>
              <View serverLog={deployment.log} />
              <Download serverLog={deployment.log}>
                <DropdownMenuItem>{translateText("Download")}</DropdownMenuItem>
              </Download>
            </>
          )}
          <DropdownMenuItem
            variant="destructive"
            onSelect={() =>
              dialog.confirm.open({
                title: translateText('Delete release [:value]', { value: deployment.release || deployment.id }),
                description: translateText('Are you sure you want to delete release :value? This will delete the release files from the server. This action cannot be undone.', { value: deployment.release || deployment.id }),
                variant: 'destructive',
                confirmLabel: translateText('Delete'),
                method: 'delete',
                url: `/servers/${encodeURIComponent(String(deployment.server_id))}/sites/${deployment.site_id}/deployments/${deployment.id}`,
              })
            }
          >
            {translateText("Delete")}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

export default function DeploymentsTable({ deployments, showPagination = true }: { deployments: InertiaTableData; showPagination?: boolean }) {
  const translateText = useTranslate();
  if (!deployments?.columns) {
    return null;
  }

  return (
    <VitoTable
      columnWidths={{ actions: '3.25rem', id: '4.5rem', deployed_by: '12%', status: '10%', duration: '8%', created_at: '15%' }}
      tableData={deployments}
      showPagination={showPagination}
      reloadOnly={['deployments']}
      cellRenderers={{
        commit: (props) => commitCell(props, translateText),
        created_at: deployedAtCell,
        deployed_by: (props) => deployedByCell(props, translateText),
        status: statusCell,
        duration: (props) => durationCell(props, translateText),
      }}
      actions={(row: Row) => <DeploymentRowActions deployment={asRow<Deployment>(row, ['id', 'site_id', 'server_id'])} />}
    />
  );
}
