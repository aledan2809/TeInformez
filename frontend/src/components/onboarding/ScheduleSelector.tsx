'use client';

import { Clock, Calendar } from 'lucide-react';

interface DeliverySchedule {
  frequency: 'realtime' | 'hourly' | 'daily' | 'weekly' | 'monthly';
  time: string;
  timezone: string;
}

interface ScheduleSelectorProps {
  schedule: DeliverySchedule;
  onScheduleChange: (schedule: DeliverySchedule) => void;
}

const FREQUENCIES = [
  { value: 'realtime', label: 'În timp real', description: 'Primești știri imediat ce apar' },
  { value: 'hourly', label: 'La fiecare oră', description: 'Rezumat orar cu ultimele știri' },
  { value: 'daily', label: 'Zilnic', description: 'O dată pe zi, la ora aleasă' },
  { value: 'weekly', label: 'Săptămânal', description: 'O dată pe săptămână' },
  { value: 'monthly', label: 'Lunar', description: 'O dată pe lună' },
] as const;

const TIMEZONES = [
  { value: 'Europe/Bucharest', label: 'București (România)' },
  { value: 'Europe/London', label: 'Londra (UK)' },
  { value: 'Europe/Paris', label: 'Paris (Franța)' },
  { value: 'Europe/Berlin', label: 'Berlin (Germania)' },
  { value: 'America/New_York', label: 'New York (SUA)' },
  { value: 'America/Los_Angeles', label: 'Los Angeles (SUA)' },
];

export default function ScheduleSelector({ schedule, onScheduleChange }: ScheduleSelectorProps) {
  const showTimePicker = ['daily', 'weekly', 'monthly'].includes(schedule.frequency);

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Când vrei să primești știri?</h2>
        <p className="text-gray-600">
          Alege frecvența și ora la care vrei să fii informat
        </p>
      </div>

      {/* Frequency selector */}
      <div className="space-y-3">
        <label className="label">
          <Calendar className="inline h-4 w-4 mr-1" />
          Frecvență
        </label>
        <div className="space-y-2">
          {FREQUENCIES.map((freq) => (
            <button
              key={freq.value}
              onClick={() =>
                onScheduleChange({
                  ...schedule,
                  frequency: freq.value,
                })
              }
              className={`w-full text-left p-4 rounded-lg border-2 transition-all ${
                schedule.frequency === freq.value
                  ? 'border-primary-600 bg-primary-50'
                  : 'border-gray-200 bg-white hover:border-gray-300'
              }`}
            >
              <div className="font-semibold text-gray-900">{freq.label}</div>
              <div className="text-sm text-gray-500 mt-1">{freq.description}</div>
            </button>
          ))}
        </div>
      </div>

      {/* Time picker (only for daily/weekly/monthly) */}
      {showTimePicker && (
        <div className="space-y-3">
          <label className="label">
            <Clock className="inline h-4 w-4 mr-1" />
            Ora de livrare
          </label>
          <input
            type="time"
            value={schedule.time}
            onChange={(e) =>
              onScheduleChange({
                ...schedule,
                time: e.target.value,
              })
            }
            className="input w-full"
          />
          <p className="text-sm text-gray-500">
            Vei primi știri în fiecare {
              schedule.frequency === 'daily' ? 'zi' :
              schedule.frequency === 'weekly' ? 'săptămână' :
              'lună'
            } la ora {schedule.time}
          </p>
        </div>
      )}

      {/* Timezone selector */}
      <div className="space-y-3">
        <label className="label">Fus orar</label>
        <select
          value={schedule.timezone}
          onChange={(e) =>
            onScheduleChange({
              ...schedule,
              timezone: e.target.value,
            })
          }
          className="input w-full"
        >
          {TIMEZONES.map((tz) => (
            <option key={tz.value} value={tz.value}>
              {tz.label}
            </option>
          ))}
        </select>
      </div>

      {/* Summary */}
      <div className="bg-primary-50 border border-primary-200 rounded-lg p-4">
        <p className="text-sm font-medium text-primary-900">
          📅 Vei primi știri:{' '}
          <strong>
            {schedule.frequency === 'realtime' && 'În timp real'}
            {schedule.frequency === 'hourly' && 'La fiecare oră'}
            {schedule.frequency === 'daily' && `Zilnic la ${schedule.time}`}
            {schedule.frequency === 'weekly' && `Săptămânal la ${schedule.time}`}
            {schedule.frequency === 'monthly' && `Lunar la ${schedule.time}`}
          </strong>
        </p>
      </div>
    </div>
  );
}
