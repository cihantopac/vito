import { useTranslate } from '@/hooks/use-translate';
import { Head, usePage } from '@inertiajs/react';
import { Server, ServerFeature } from '@/types/server';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import ServerLayout from '@/layouts/server/layout';
import FeaturesCard from '@/components/features-card';
import { useDialog } from '@/hooks/use-dialog';

export default function ServerFeatures() {
  const translateText = useTranslate();
  const page = usePage<{
    server: Server;
    features: {
      [key: string]: ServerFeature;
    };
  }>();
  const dialog = useDialog();

  return (
    <ServerLayout>
      <Head title={`Features - ${page.props.server.name}`} />

      <Container>
        <HeaderContainer>
          <Heading title={translateText('Features')} description="Your server has some features enabled by Pure or other plugins" />
        </HeaderContainer>

        <FeaturesCard
          title="Server features"
          features={page.props.features}
          onActionSelect={(featureId, actionId, action) => 
            dialog.serverFeatureAction.open({ server: page.props.server, featureId, actionId, action })
          }
        />
      </Container>
    </ServerLayout>
  );
}
