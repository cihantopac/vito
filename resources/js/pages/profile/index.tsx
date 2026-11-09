import { useTranslate } from '@/hooks/use-translate';
import { Head, Link, usePage } from '@inertiajs/react';
import SettingsLayout from '@/layouts/settings/layout';
import AuthLayout from '@/layouts/auth/layout';
import Container from '@/components/container';
import UpdatePassword from '@/pages/profile/components/update-password';
import UpdateProfile from '@/pages/profile/components/update-profile';
import Heading from '@/components/heading';
import TwoFactor from '@/pages/profile/components/two-factor';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useState } from 'react';
import { SharedData } from '@/types';
import { Button } from '@/components/ui/button';

export default function Profile() {
  const t = useTranslate();
  const page = usePage<SharedData>();
  const mustChangePassword = page.props.auth.user.must_change_password;
  const [tab, setTab] = useState(mustChangePassword ? 'password' : 'info');

  if (mustChangePassword) {
    return (
      <AuthLayout title={t('Change your password')} description={t('Set a new password before continuing to your account.')} showFooter={false}>
        <Head title={t('Change your password')} />
        <UpdatePassword />
        <Button variant="outline" className="w-full" asChild>
          <Link href="/logout" method="post" as="button">{t('Log out')}</Link>
        </Button>
      </AuthLayout>
    );
  }

  return (
    <SettingsLayout>
      <Head title={t('Profile settings')} />
      <Container>
        <div className="flex items-start justify-between">
          <Heading title={t('Profile settings')} description={t('Manage your profile settings.')} />
          <div className="flex items-center gap-2">
          </div>
        </div>
        <Tabs value={tab} onValueChange={setTab}>
          <TabsList>
            <TabsTrigger value="info">
              {t('Info')}
            </TabsTrigger>
            <TabsTrigger value="password">{t('Password')}</TabsTrigger>
            <TabsTrigger value="two_factor">
              {t('Two Factor')}
            </TabsTrigger>
          </TabsList>
          <TabsContent value="info" className="space-y-4">
            <UpdateProfile />
          </TabsContent>
          <TabsContent value="password">
            <UpdatePassword />
          </TabsContent>
          <TabsContent value="two_factor">
            <TwoFactor />
          </TabsContent>
        </Tabs>
      </Container>
    </SettingsLayout>
  );
}
