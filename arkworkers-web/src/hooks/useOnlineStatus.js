import { useEffect, useState } from 'react';
import { onlineManager } from '@tanstack/react-query';

export function useOnlineStatus() {
  const [isOnline, setIsOnline] = useState(onlineManager.isOnline());

  useEffect(() => {
    return onlineManager.subscribe(setIsOnline);
  }, []);

  return isOnline;
}
