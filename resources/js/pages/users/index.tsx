import { useTranslate } from '@/hooks/use-translate';
import Layout from '@/layouts/app/layout';
import { Head } from '@inertiajs/react';
import Container from '@/components/container';
import Heading from '@/components/heading';
import UsersList from '@/pages/users/components/list';
import { Button } from '@/components/ui/button';
import { useDialog } from '@/hooks/use-dialog';
import RolePermissionsTable from '@/pages/users/components/role-permissions-table';

export default function Users() {
  const t = useTranslate();
  const dialog = useDialog();
  return (
    <Layout>
      <Head title={t('Users')} />

      <Container className="space-y-6">
        <div className="flex items-start justify-between">
          <Heading title={t('Users')} />
          <Button onClick={() => dialog.userForm.open({})}>{t('Create user')}</Button>
        </div>
        <UsersList />
        <RolePermissionsTable />
      </Container>
    </Layout>
  );
}
