import { useState, useEffect, useRef, useMemo, useCallback } from 'react';

/**
 * VirtualList Component
 * Renders massive collections (10,000+ songs or albums) with constant 60fps smoothness
 * by strictly rendering only the visible viewport window + small overscan buffer into the DOM.
 * Works seamlessly with window, document, or custom scrolling containers (e.g. main.overflow-y-auto).
 */
export default function VirtualList({
  items = [],
  itemHeight = 58,
  renderItem,
  overscan = 8,
  className = '',
  onLoadMore,
  hasMore = false,
  isLoading = false,
  emptyMessage = 'No items found'
}) {
  const containerRef = useRef(null);
  const [scrollTop, setScrollTop] = useState(0);
  const [viewportHeight, setViewportHeight] = useState(800);
  const rafIdRef = useRef(null);

  // Find scroll container (either closest scrollable parent or window)
  const getScrollContainer = useCallback(() => {
    if (!containerRef.current) return null;
    let parent = containerRef.current.parentElement;
    while (parent) {
      const overflowY = window.getComputedStyle(parent).overflowY;
      if (overflowY === 'auto' || overflowY === 'scroll') {
        return parent;
      }
      parent = parent.parentElement;
    }
    return window;
  }, []);

  const handleScroll = useCallback(() => {
    if (rafIdRef.current) cancelAnimationFrame(rafIdRef.current);

    rafIdRef.current = requestAnimationFrame(() => {
      const scrollParent = getScrollContainer();
      if (!scrollParent || !containerRef.current) return;

      if (scrollParent === window) {
        const rect = containerRef.current.getBoundingClientRect();
        const topOffset = Math.max(0, -rect.top);
        setScrollTop(topOffset);
        setViewportHeight(window.innerHeight);
      } else {
        const parentRect = scrollParent.getBoundingClientRect();
        const containerRect = containerRef.current.getBoundingClientRect();
        const offset = Math.max(0, parentRect.top - containerRect.top);
        setScrollTop(offset);
        setViewportHeight(scrollParent.clientHeight);
      }
    });
  }, [getScrollContainer]);

  useEffect(() => {
    const scrollParent = getScrollContainer();
    if (!scrollParent) return;

    handleScroll();

    scrollParent.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll);

    return () => {
      if (rafIdRef.current) cancelAnimationFrame(rafIdRef.current);
      scrollParent.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', handleScroll);
    };
  }, [getScrollContainer, handleScroll]);

  const totalHeight = items.length * itemHeight;

  // Compute visible index range
  const { startIndex, endIndex } = useMemo(() => {
    if (items.length === 0) return { startIndex: 0, endIndex: 0 };
    const start = Math.max(0, Math.floor(scrollTop / itemHeight) - overscan);
    const visibleCount = Math.ceil(viewportHeight / itemHeight);
    const end = Math.min(items.length, start + visibleCount + overscan * 2);
    return { startIndex: start, endIndex: end };
  }, [scrollTop, itemHeight, viewportHeight, items.length, overscan]);

  // Trigger infinite loading when nearing end of the catalog
  useEffect(() => {
    if (hasMore && !isLoading && onLoadMore && items.length > 0) {
      if (endIndex >= items.length - 15) {
        onLoadMore();
      }
    }
  }, [endIndex, items.length, hasMore, isLoading, onLoadMore]);

  const visibleItems = useMemo(() => {
    const slice = [];
    for (let i = startIndex; i < endIndex; i++) {
      if (items[i]) {
        slice.push({ item: items[i], index: i });
      }
    }
    return slice;
  }, [items, startIndex, endIndex]);

  if (items.length === 0 && !isLoading) {
    return (
      <div className="py-16 text-center text-slate-400 text-sm">
        {emptyMessage}
      </div>
    );
  }

  return (
    <div ref={containerRef} className={`relative w-full ${className}`}>
      {/* Spacer div with total catalog height to maintain accurate scrollbar physics */}
      <div style={{ height: `${totalHeight}px`, width: '100%', position: 'relative' }}>
        {visibleItems.map(({ item, index }) => (
          <div
            key={item.id || `virtual-row-${index}`}
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              width: '100%',
              height: `${itemHeight}px`,
              transform: `translate3d(0, ${index * itemHeight}px, 0)`
            }}
          >
            {renderItem(item, index)}
          </div>
        ))}
      </div>

      {isLoading && (
        <div className="flex justify-center py-6">
          <div className="w-7 h-7 border-3 border-purple-500 border-t-transparent rounded-full animate-spin" />
        </div>
      )}
    </div>
  );
}

