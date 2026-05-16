import { Suspense } from 'react';
import SubscribeContent from './SubscribeContent';

export default function SubscribePage() {
  return (
    <Suspense>
      <SubscribeContent />
    </Suspense>
  );
}
