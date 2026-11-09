import { useTranslate } from '@/hooks/use-translate';
import { User } from '@/types/user';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { TableActionTrigger } from '@/components/table-action-trigger';
import { useDialog } from '@/hooks/use-dialog';

export default function UserActions({ user }: { user: User }) {
  const t = useTranslate();
  const dialog = useDialog();
  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <TableActionTrigger />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start">
        <DropdownMenuItem onSelect={() => dialog.userForm.open({ user })}>{t('Edit')}</DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem variant="destructive" onSelect={() => dialog.confirm.open({
          title: 'Delete user',
          description: `Delete ${user.name}? This cannot be undone.`,
          confirmLabel: 'Delete',
          variant: 'destructive',
          method: 'delete',
          url: `/admin/users/${user.id}`,
        })}>{t('Delete')}</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
