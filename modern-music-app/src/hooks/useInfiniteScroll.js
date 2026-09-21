import { useEffect, useRef, useCallback } from 'react';

/**
 * useInfiniteScroll
 * Automatically triggers onLoadMore when a sentinel element enters the viewport.
 * Uses native browser IntersectionObserver for maximum performance with 0 dependencies.
 *
 * @param {Function} onLoadMore Callback function when sentinel is visible
 * @param {boolean} hasMore Whether there are more items to fetch
 * @param {boolean} isLoading Whether a fetch is currently in progress
 * @param {string} rootMargin Margin around the root (default: '400px' for pre-fetching before bottom)
 * @returns {React.MutableRefObject} Ref to attach to the sentinel <div>
 */
export function useInfiniteScroll(onLoadMore, hasMore, isLoading, rootMargin = '400px') {
  const sentinelRef = useRef(null);
  const callbackRef = useRef(onLoadMore);

  useEffect(() => {
    callbackRef.current = onLoadMore;
  }, [onLoadMore]);

  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel || !hasMore || isLoading) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const first = entries[0];
        if (first && first.isIntersecting && hasMore && !isLoading) {
          if (callbackRef.current) {
            callbackRef.current();
          }
        }
      },
      {
        root: null,
        rootMargin,
        threshold: 0.05
      }
    );

    observer.observe(sentinel);

    return () => {
      observer.disconnect();
    };
  }, [hasMore, isLoading, rootMargin]);

  return sentinelRef;
}

export default useInfiniteScroll;

