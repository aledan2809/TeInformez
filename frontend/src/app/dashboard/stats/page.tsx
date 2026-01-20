'use client';

import { BarChart3 } from 'lucide-react';

export default function StatsPage() {
  return (
    <div className="p-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Statistici</h1>

      <div className="card text-center py-12">
        <BarChart3 className="h-16 w-16 text-gray-300 mx-auto mb-4" />
        <h2 className="text-xl font-semibold text-gray-900 mb-2">
          Statistici în curând disponibile
        </h2>
        <p className="text-gray-600 max-w-md mx-auto">
          Aici vei putea vedea statistici despre știrile tale: câte ai primit, pe ce canale,
          categoriile cele mai populare și multe altele.
        </p>
        <p className="text-sm text-gray-500 mt-4">
          Această funcționalitate va fi disponibilă în Phase C (Delivery System)
        </p>
      </div>
    </div>
  );
}
