'use client';

import { useState, useEffect } from 'react';
import { Save, Download, Trash2, Loader2 } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';
import ScheduleSelector from '@/components/onboarding/ScheduleSelector';
import ChannelSelector from '@/components/onboarding/ChannelSelector';

export default function SettingsPage() {
  const { user, fetchUser } = useAuthStore();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Preferences state
  const [schedule, setSchedule] = useState(user?.preferences?.delivery_schedule || {
    frequency: 'daily',
    time: '14:00',
    timezone: 'Europe/Bucharest',
  });
  const [selectedChannels, setSelectedChannels] = useState<string[]>(
    user?.preferences?.delivery_channels || ['email']
  );

  useEffect(() => {
    if (user?.preferences) {
      setSchedule(user.preferences.delivery_schedule);
      setSelectedChannels(user.preferences.delivery_channels);
    }
  }, [user]);

  const handleToggleChannel = (channelId: string) => {
    setSelectedChannels((prev) =>
      prev.includes(channelId) ? prev.filter((c) => c !== channelId) : [...prev, channelId]
    );
  };

  const handleSave = async () => {
    setIsLoading(true);
    setError('');
    setSuccess('');

    try {
      await api.updatePreferences({
        delivery_schedule: schedule,
        delivery_channels: selectedChannels,
      });

      setSuccess('Setările au fost salvate cu succes!');
      await fetchUser(); // Refresh user data
    } catch (err: any) {
      setError(err.response?.data?.message || 'A apărut o eroare');
    } finally {
      setIsLoading(false);
    }
  };

  const handleExportData = async () => {
    try {
      const data = await api.exportUserData();
      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: 'application/json',
      });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `teinformez-data-${new Date().toISOString()}.json`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      setError('Exportul datelor a eșuat');
    }
  };

  const handleDeleteAccount = async () => {
    const confirmation = prompt(
      'Ești sigur că vrei să ștergi contul? Această acțiune este IREVERSIBILĂ.\n\nScrie "ȘTERGE CONTUL" pentru confirmare:'
    );

    if (confirmation !== 'ȘTERGE CONTUL') {
      return;
    }

    try {
      await api.deleteAccount();
      window.location.href = '/';
    } catch (err) {
      setError('Ștergerea contului a eșuat');
    }
  };

  return (
    <div className="p-8 max-w-4xl">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Setări</h1>

      {/* Messages */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
          {error}
        </div>
      )}
      {success && (
        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
          {success}
        </div>
      )}

      {/* Account info */}
      <div className="card mb-6">
        <h2 className="text-xl font-semibold mb-4">Informații cont</h2>
        <div className="space-y-3">
          <div>
            <label className="label">Email</label>
            <input type="email" value={user?.email || ''} disabled className="input" />
          </div>
          <div>
            <label className="label">Nume</label>
            <input type="text" value={user?.name || ''} disabled className="input" />
          </div>
          <p className="text-sm text-gray-500">
            Membru din {new Date(user?.registered_at || '').toLocaleDateString('ro-RO')}
          </p>
        </div>
      </div>

      {/* Delivery schedule */}
      <div className="card mb-6">
        <ScheduleSelector schedule={schedule} onScheduleChange={setSchedule} />
      </div>

      {/* Delivery channels */}
      <div className="card mb-6">
        <ChannelSelector
          selectedChannels={selectedChannels}
          onToggleChannel={handleToggleChannel}
        />
      </div>

      {/* Save button */}
      <div className="flex justify-end mb-8">
        <button onClick={handleSave} disabled={isLoading} className="btn-primary">
          {isLoading ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Salvare...
            </>
          ) : (
            <>
              <Save className="h-4 w-4 mr-2" />
              Salvează modificările
            </>
          )}
        </button>
      </div>

      {/* GDPR Section */}
      <div className="card border-2 border-gray-300">
        <h2 className="text-xl font-semibold mb-4">Date personale (GDPR)</h2>
        <div className="space-y-4">
          <div>
            <h3 className="font-semibold text-gray-900 mb-2">Exportă datele tale</h3>
            <p className="text-sm text-gray-600 mb-3">
              Descarcă toate datele tale personale în format JSON
            </p>
            <button onClick={handleExportData} className="btn-outline">
              <Download className="h-4 w-4 mr-2" />
              Descarcă date
            </button>
          </div>

          <hr className="border-gray-200" />

          <div>
            <h3 className="font-semibold text-red-600 mb-2">Șterge contul</h3>
            <p className="text-sm text-gray-600 mb-3">
              Ștergerea contului este IREVERSIBILĂ. Toate datele tale vor fi șterse permanent.
            </p>
            <button
              onClick={handleDeleteAccount}
              className="btn bg-red-600 text-white hover:bg-red-700"
            >
              <Trash2 className="h-4 w-4 mr-2" />
              Șterge contul permanent
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
