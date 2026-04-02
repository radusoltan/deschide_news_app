'use client';

import { Badge } from 'flowbite-react';

interface TopCountriesTableProps {
  data: Array<{
    countryCode: string;
    count: number;
  }>;
}

// Country code to name mapping (extend as needed)
const COUNTRY_NAMES: Record<string, string> = {
  MD: 'Moldova',
  RO: 'România',
  RU: 'Rusia',
  US: 'SUA',
  UA: 'Ucraina',
  DE: 'Germania',
  FR: 'Franța',
  GB: 'Marea Britanie',
  IT: 'Italia',
  ES: 'Spania',
  PL: 'Polonia',
  TR: 'Turcia',
  CA: 'Canada',
  AU: 'Australia',
  BR: 'Brazilia',
  IN: 'India',
  CN: 'China',
  JP: 'Japonia',
  KR: 'Coreea de Sud',
  MX: 'Mexic',
  NL: 'Olanda',
  SE: 'Suedia',
  NO: 'Norvegia',
  FI: 'Finlanda',
  DK: 'Danemarca',
  CH: 'Elveția',
  AT: 'Austria',
  BE: 'Belgia',
  PT: 'Portugalia',
  GR: 'Grecia',
  CZ: 'Cehia',
  HU: 'Ungaria',
  BG: 'Bulgaria',
  RS: 'Serbia',
  HR: 'Croația',
  SK: 'Slovacia',
  SI: 'Slovenia',
  LT: 'Lituania',
  LV: 'Letonia',
  EE: 'Estonia',
  IE: 'Irlanda',
  IL: 'Israel',
  SA: 'Arabia Saudită',
  AE: 'Emiratele Arabe Unite',
  ZA: 'Africa de Sud',
  AR: 'Argentina',
  CL: 'Chile',
  CO: 'Columbia',
  TH: 'Thailanda',
  MY: 'Malaysia',
  SG: 'Singapore',
  PH: 'Filipine',
  VN: 'Vietnam',
  ID: 'Indonezia',
  NZ: 'Noua Zeelandă',
  UNKNOWN: 'Necunoscut',
};

export default function TopCountriesTable({ data }: TopCountriesTableProps) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
          Top Țări
        </h3>
        <div className="h-64 flex items-center justify-center bg-surface-sunken dark:bg-gray-700/50 rounded-lg">
          <p className="text-secondary dark:text-gray-400">
            Nu există date disponibile
          </p>
        </div>
      </div>
    );
  }

  const total = data.reduce((sum, item) => sum + item.count, 0);

  // Sort by count and take top 15
  const topCountries = [...data]
    .sort((a, b) => b.count - a.count)
    .slice(0, 15);

  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow overflow-hidden">
      <div className="p-6 border-b border-gray-200 dark:border-gray-700">
        <h3 className="text-lg font-semibold text-primary dark:text-primary-dark">
          Top Țări
        </h3>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Distribuția geografică a clicurilor
        </p>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-sm text-left text-secondary dark:text-gray-400">
          <thead className="text-xs text-primary uppercase bg-surface-sunken dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="px-6 py-3">
                #
              </th>
              <th scope="col" className="px-6 py-3">
                Cod
              </th>
              <th scope="col" className="px-6 py-3">
                Țară
              </th>
              <th scope="col" className="px-6 py-3">
                Clicuri
              </th>
              <th scope="col" className="px-6 py-3">
                Procent
              </th>
              <th scope="col" className="px-6 py-3">
                <span className="sr-only">Vizualizare</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {topCountries.map((country, index) => {
              const percentage = ((country.count / total) * 100).toFixed(1);
              return (
                <tr
                  key={country.countryCode}
                  className="bg-surface border-b dark:bg-surface-dark dark:border-gray-700 hover:bg-surface-sunken dark:hover:bg-gray-600"
                >
                  <td className="px-6 py-4 font-medium text-primary dark:text-primary-dark">
                    {index + 1}
                  </td>
                  <td className="px-6 py-4">
                    <Badge
                      color="gray"
                      className="font-mono w-fit"
                    >
                      {country.countryCode}
                    </Badge>
                  </td>
                  <td className="px-6 py-4 font-medium text-primary dark:text-primary-dark">
                    {COUNTRY_NAMES[country.countryCode] ||
                      country.countryCode}
                  </td>
                  <td className="px-6 py-4">
                    <span className="font-semibold text-primary dark:text-primary-dark">
                      {country.count.toLocaleString('ro-RO')}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 max-w-[100px]">
                        <div
                          className="bg-blue-600 h-2 rounded-full"
                          style={{ width: `${percentage}%` }}
                        ></div>
                      </div>
                      <span className="text-gray-600 dark:text-gray-400 text-xs">
                        {percentage}%
                      </span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    {index < 3 && (
                      <Badge
                        color={
                          index === 0
                            ? 'success'
                            : index === 1
                            ? 'info'
                            : 'warning'
                        }
                      >
                        {index === 0 ? '🥇' : index === 1 ? '🥈' : '🥉'}
                      </Badge>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Summary footer */}
      {data.length > 15 && (
        <div className="px-6 py-4 bg-surface-sunken dark:bg-gray-700/50 text-sm text-gray-600 dark:text-gray-400 text-center">
          Afișare top 15 din {data.length} țări
        </div>
      )}
    </div>
  );
}
