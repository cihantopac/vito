import { useTranslate } from '@/hooks/use-translate';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Plugin } from '@/types/plugin';
import { useDialog } from '@/hooks/use-dialog';

export default function DeleteLogs({ plugin }: { plugin: Plugin }) {
  const translateText = useTranslate();
  const dialog = useDialog();

  return (
    <DropdownMenuItem
      onSelect={() =>
        dialog.confirm.open({
          title: 'Delete Error Logs',
          description: `Are you sure you want to delete the error logs for the plugin ${plugin.name ?? plugin.folder}?`,
          variant: 'destructive',
          confirmLabel: 'Delete Logs',
          method: 'delete',
          url: "/admin/plugins/logs",
          data: { id: plugin.id },
        })
      }
    >
      {translateText("Delete Logs")}
    </DropdownMenuItem>
  );
}
