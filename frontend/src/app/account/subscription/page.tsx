import { Suspense } from 'react';
import SubscriptionContent from './SubscriptionContent';

export default function SubscriptionPage() {
  return (
    <Suspense>
      <SubscriptionContent />
    </Suspense>
  );
}
