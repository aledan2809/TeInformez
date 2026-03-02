import { useState, useEffect, useRef } from 'react';

export function useAnimatedCounter(target: number, duration: number = 800): number {
  const [count, setCount] = useState(0);
  const prevTarget = useRef(0);

  useEffect(() => {
    if (target === prevTarget.current) return;

    const start = prevTarget.current;
    const diff = target - start;
    if (diff === 0) return;

    const startTime = performance.now();

    const step = (time: number) => {
      const elapsed = time - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // easeOutCubic
      const eased = 1 - Math.pow(1 - progress, 3);
      setCount(Math.round(start + diff * eased));

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        prevTarget.current = target;
      }
    };

    requestAnimationFrame(step);
  }, [target, duration]);

  return count;
}
