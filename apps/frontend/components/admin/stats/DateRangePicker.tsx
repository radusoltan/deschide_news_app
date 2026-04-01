/**
 * Date Range Picker Component
 * Client component for interactive date range selection
 */

'use client';

interface DateRangePickerProps {
  defaultRange: string;
}

export function DateRangePicker({ defaultRange }: DateRangePickerProps) {
  const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const url = new URL(window.location.href);
    url.searchParams.set('range', e.target.value);
    window.location.href = url.toString();
  };

  return (
    <div className="flex items-center gap-2">
      <label htmlFor="dateRange" className="text-sm font-medium text-primary">
        Date Range:
      </label>
      <select
        id="dateRange"
        defaultValue={defaultRange}
        onChange={handleChange}
        className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-surface text-sm"
      >
        <option value="today">Today</option>
        <option value="yesterday">Yesterday</option>
        <option value="7days">Last 7 Days</option>
        <option value="30days">Last 30 Days</option>
      </select>
    </div>
  );
}
