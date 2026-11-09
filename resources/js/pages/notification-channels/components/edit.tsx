import { useTranslate } from '@/hooks/use-translate';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { NotificationChannel } from '@/types/notification-channel';
import { useDialog } from '@/hooks/use-dialog';

export default function Edit({ notificationChannel }: { notificationChannel: NotificationChannel }) {
  const t = useTranslate();
  const dialog = useDialog();

  return <DropdownMenuItem onSelect={() => dialog.notificationChannelEdit.open({ notificationChannel })}>{t('Edit')}</DropdownMenuItem>;
}
