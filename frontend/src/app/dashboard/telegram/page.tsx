'use client';

import { useEffect, useMemo, useState } from 'react';
import { Bot, Download, Loader2, MessageSquare, RefreshCcw, Save, Send, Users } from 'lucide-react';
import { api } from '@/lib/api';
import type {
  TelegramConfig,
  TelegramGroup,
  TelegramReadReport,
  TelegramSendReport,
  TelegramReadGroupReport,
} from '@/types';

type Mode = 'sequential' | 'parallel';

const REPORT_DATE_FORMAT = new Intl.DateTimeFormat('ro-RO', {
  dateStyle: 'medium',
  timeStyle: 'short',
});

export default function TelegramDashboardPage() {
  const [config, setConfig] = useState<TelegramConfig | null>(null);
  const [botTokenInput, setBotTokenInput] = useState('');
  const [groupsDraft, setGroupsDraft] = useState('');
  const [selectedGroupIds, setSelectedGroupIds] = useState<string[]>([]);
  const [mode, setMode] = useState<Mode>('parallel');
  const [messageText, setMessageText] = useState('');
  const [readLimit, setReadLimit] = useState(25);
  const [loadingConfig, setLoadingConfig] = useState(true);
  const [savingConfig, setSavingConfig] = useState(false);
  const [discovering, setDiscovering] = useState(false);
  const [reading, setReading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [readReport, setReadReport] = useState<TelegramReadReport | null>(null);
  const [sendReport, setSendReport] = useState<TelegramSendReport | null>(null);

  useEffect(() => {
    const load = async () => {
      setLoadingConfig(true);
      setError(null);
      try {
        const result = await api.getTelegramConfig();
        setConfig(result);
        setGroupsDraft(toGroupText(result.groups));
        setSelectedGroupIds(result.groups.map((group) => group.id));
      } catch (err) {
        setError((err as Error).message || 'Nu s-a putut încărca configurația Telegram.');
      } finally {
        setLoadingConfig(false);
      }
    };

    void load();
  }, []);

  const groupsFromDraft = useMemo(() => parseGroupsText(groupsDraft), [groupsDraft]);

  const effectiveGroups = useMemo(() => {
    if (groupsFromDraft.length > 0) {
      return groupsFromDraft;
    }
    return config?.groups ?? [];
  }, [config?.groups, groupsFromDraft]);

  const allSelected = effectiveGroups.length > 0 && selectedGroupIds.length === effectiveGroups.length;

  const onToggleGroup = (groupId: string) => {
    setSelectedGroupIds((prev) => {
      if (prev.includes(groupId)) {
        return prev.filter((id) => id !== groupId);
      }
      return [...prev, groupId];
    });
  };

  const onToggleAll = () => {
    if (allSelected) {
      setSelectedGroupIds([]);
      return;
    }
    setSelectedGroupIds(effectiveGroups.map((group) => group.id));
  };

  const onSaveConfig = async () => {
    setSavingConfig(true);
    setError(null);
    setSuccess(null);

    try {
      const payloadGroups = parseGroupsText(groupsDraft);
      const result = await api.saveTelegramConfig({
        bot_token: botTokenInput.trim() === '' ? undefined : botTokenInput.trim(),
        groups: payloadGroups,
      });

      setConfig(result);
      setGroupsDraft(toGroupText(result.groups));
      setSelectedGroupIds(result.groups.map((group) => group.id));
      setBotTokenInput('');
      setSuccess('Configurația Telegram a fost salvată.');
    } catch (err) {
      setError((err as Error).message || 'Nu s-a putut salva configurația.');
    } finally {
      setSavingConfig(false);
    }
  };

  const onDiscoverGroups = async () => {
    setDiscovering(true);
    setError(null);
    setSuccess(null);

    try {
      const result = await api.discoverTelegramGroups();
      const nextConfig = {
        has_token: config?.has_token ?? true,
        token_mask: config?.token_mask ?? null,
        groups: result.groups,
      };
      setConfig(nextConfig);
      setGroupsDraft(toGroupText(result.groups));
      setSelectedGroupIds(result.groups.map((group) => group.id));
      setSuccess(`Grupuri descoperite: ${result.discovered_now}`);
    } catch (err) {
      setError((err as Error).message || 'Nu s-au putut descoperi grupurile Telegram.');
    } finally {
      setDiscovering(false);
    }
  };

  const onReadMessages = async () => {
    setReading(true);
    setError(null);
    setSuccess(null);

    try {
      const report = await api.readTelegramMessages({
        group_ids: selectedGroupIds,
        mode,
        limit: readLimit,
      });
      setReadReport(report);
      setSuccess('Citirea mesajelor s-a finalizat.');
    } catch (err) {
      setError((err as Error).message || 'Nu s-au putut citi mesajele.');
    } finally {
      setReading(false);
    }
  };

  const onSendMessage = async () => {
    if (messageText.trim() === '') {
      setError('Mesajul este obligatoriu.');
      return;
    }

    setSending(true);
    setError(null);
    setSuccess(null);

    try {
      const report = await api.sendTelegramMessage({
        group_ids: selectedGroupIds,
        mode,
        text: messageText.trim(),
      });
      setSendReport(report);
      setSuccess('Trimiterea mesajului s-a finalizat.');
    } catch (err) {
      setError((err as Error).message || 'Nu s-a putut trimite mesajul.');
    } finally {
      setSending(false);
    }
  };

  const downloadReportJson = (filename: string, data: TelegramReadReport | TelegramSendReport) => {
    const content = JSON.stringify(data, null, 2);
    const blob = new Blob([content], { type: 'application/json;charset=utf-8' });
    downloadBlob(filename, blob);
  };

  const downloadReadReportCsv = (report: TelegramReadReport) => {
    const rows: string[][] = [['group_id', 'group_title', 'message_id', 'date', 'from', 'type', 'text']];

    report.groups.forEach((group) => {
      group.messages.forEach((message) => {
        rows.push([
          group.group_id,
          group.title,
          String(message.message_id),
          message.date ?? '',
          message.from,
          message.type,
          message.text,
        ]);
      });
    });

    const csv = toCsv(rows);
    downloadBlob(`telegram-read-report-${Date.now()}.csv`, new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  };

  const downloadSendReportCsv = (report: TelegramSendReport) => {
    const rows: string[][] = [['group_id', 'group_title', 'success', 'message_id', 'error']];

    report.results.forEach((result) => {
      rows.push([
        result.group_id,
        result.title,
        result.success ? 'true' : 'false',
        result.message_id ? String(result.message_id) : '',
        result.error ?? '',
      ]);
    });

    const csv = toCsv(rows);
    downloadBlob(`telegram-send-report-${Date.now()}.csv`, new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  };

  if (loadingConfig) {
    return (
      <div className="p-8 flex items-center gap-3 text-gray-600 dark:text-gray-300">
        <Loader2 className="h-5 w-5 animate-spin" />
        Se încarcă configurarea Telegram...
      </div>
    );
  }

  return (
    <div className="p-8 space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Telegram Workspace</h1>
        <p className="text-gray-600 dark:text-gray-400 mt-2">
          Citește și trimite mesaje în grupuri Telegram, pe rând sau în paralel, cu raport downloadabil.
        </p>
      </div>

      {error && <div className="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {success && <div className="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-700">{success}</div>}

      <section className="card space-y-4">
        <div className="flex items-center gap-2 text-gray-900 dark:text-gray-100 font-semibold text-lg">
          <Bot className="h-5 w-5 text-primary-600" />
          Configurare bot și grupuri
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <label className="block">
            <span className="text-sm font-medium text-gray-700 dark:text-gray-200">Token bot Telegram</span>
            <input
              type="password"
              value={botTokenInput}
              onChange={(event) => setBotTokenInput(event.target.value)}
              placeholder={config?.token_mask ? `Token salvat: ${config.token_mask}` : '123456789:AA...'}
              className="input mt-1"
            />
          </label>

          <div className="rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm text-gray-600 dark:text-gray-300">
            <p>
              Token salvat: <span className="font-medium">{config?.has_token ? 'Da' : 'Nu'}</span>
            </p>
            <p>
              Grupuri în profil: <span className="font-medium">{config?.groups.length ?? 0}</span>
            </p>
          </div>
        </div>

        <label className="block">
          <span className="text-sm font-medium text-gray-700 dark:text-gray-200">Grupuri (format: id|titlu, câte unul pe linie)</span>
          <textarea
            value={groupsDraft}
            onChange={(event) => setGroupsDraft(event.target.value)}
            rows={6}
            className="input mt-1"
            placeholder={'-1001234567890|Grup Marketing\n-1002234567890|Grup Sales'}
          />
        </label>

        <div className="flex flex-wrap gap-3">
          <button
            type="button"
            onClick={onSaveConfig}
            disabled={savingConfig}
            className="btn-primary inline-flex items-center gap-2 disabled:opacity-60"
          >
            {savingConfig ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            Salvează configurarea
          </button>
          <button
            type="button"
            onClick={onDiscoverGroups}
            disabled={discovering}
            className="btn-secondary inline-flex items-center gap-2 disabled:opacity-60"
          >
            {discovering ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
            Descoperă grupuri din update-uri
          </button>
        </div>
      </section>

      <section className="card space-y-4">
        <div className="flex items-center gap-2 text-gray-900 dark:text-gray-100 font-semibold text-lg">
          <Users className="h-5 w-5 text-primary-600" />
          Selectare grupuri și mod de execuție
        </div>

        <div className="flex flex-wrap items-center gap-4">
          <label className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" checked={allSelected} onChange={onToggleAll} />
            Selectează toate
          </label>

          <label className="text-sm text-gray-700 dark:text-gray-200 inline-flex items-center gap-2">
            Mod
            <select value={mode} onChange={(event) => setMode(event.target.value as Mode)} className="input py-2 px-3">
              <option value="parallel">Toate în același timp</option>
              <option value="sequential">Pe rând</option>
            </select>
          </label>

          <label className="text-sm text-gray-700 dark:text-gray-200 inline-flex items-center gap-2">
            Limită citire / grup
            <input
              type="number"
              min={1}
              max={100}
              value={readLimit}
              onChange={(event) => setReadLimit(Math.max(1, Math.min(100, Number(event.target.value) || 1)))}
              className="input py-2 px-3 w-24"
            />
          </label>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {effectiveGroups.map((group) => (
            <label
              key={group.id}
              className="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 inline-flex items-start gap-2"
            >
              <input
                type="checkbox"
                checked={selectedGroupIds.includes(group.id)}
                onChange={() => onToggleGroup(group.id)}
              />
              <span className="text-sm text-gray-700 dark:text-gray-200">
                <span className="font-medium block">{group.title}</span>
                <span className="text-xs text-gray-500 dark:text-gray-400">{group.id}</span>
              </span>
            </label>
          ))}
        </div>

        {effectiveGroups.length === 0 && (
          <p className="text-sm text-gray-600 dark:text-gray-400">Adaugă sau descoperă grupuri pentru a continua.</p>
        )}
      </section>

      <section className="card space-y-4">
        <div className="flex items-center gap-2 text-gray-900 dark:text-gray-100 font-semibold text-lg">
          <MessageSquare className="h-5 w-5 text-primary-600" />
          Citire mesaje
        </div>

        <button
          type="button"
          onClick={onReadMessages}
          disabled={reading || selectedGroupIds.length === 0}
          className="btn-primary inline-flex items-center gap-2 disabled:opacity-60"
        >
          {reading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
          Citește mesaje
        </button>

        {readReport && (
          <div className="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="text-sm text-gray-700 dark:text-gray-200">
                Raport: {REPORT_DATE_FORMAT.format(new Date(readReport.generated_at))} | Grupuri: {readReport.groups_count} | Mesaje: {readReport.messages_total}
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => downloadReadReportCsv(readReport)}
                  className="btn-secondary inline-flex items-center gap-2"
                >
                  <Download className="h-4 w-4" />
                  CSV
                </button>
                <button
                  type="button"
                  onClick={() => downloadReportJson(`telegram-read-report-${Date.now()}.json`, readReport)}
                  className="btn-secondary inline-flex items-center gap-2"
                >
                  <Download className="h-4 w-4" />
                  JSON
                </button>
              </div>
            </div>

            <div className="space-y-3">
              {readReport.groups.map((group) => (
                <ReadGroupCard key={group.group_id} group={group} />
              ))}
            </div>
          </div>
        )}
      </section>

      <section className="card space-y-4">
        <div className="flex items-center gap-2 text-gray-900 dark:text-gray-100 font-semibold text-lg">
          <Send className="h-5 w-5 text-primary-600" />
          Trimitere mesaj
        </div>

        <textarea
          value={messageText}
          onChange={(event) => setMessageText(event.target.value)}
          rows={4}
          className="input"
          placeholder="Scrie mesajul care va fi trimis în grupurile selectate"
        />

        <button
          type="button"
          onClick={onSendMessage}
          disabled={sending || selectedGroupIds.length === 0 || messageText.trim() === ''}
          className="btn-primary inline-flex items-center gap-2 disabled:opacity-60"
        >
          {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          Trimite mesajul
        </button>

        {sendReport && (
          <div className="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="text-sm text-gray-700 dark:text-gray-200">
                Raport: {REPORT_DATE_FORMAT.format(new Date(sendReport.generated_at))} | Trimise: {sendReport.sent_count} | Eșuate: {sendReport.failed_count}
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => downloadSendReportCsv(sendReport)}
                  className="btn-secondary inline-flex items-center gap-2"
                >
                  <Download className="h-4 w-4" />
                  CSV
                </button>
                <button
                  type="button"
                  onClick={() => downloadReportJson(`telegram-send-report-${Date.now()}.json`, sendReport)}
                  className="btn-secondary inline-flex items-center gap-2"
                >
                  <Download className="h-4 w-4" />
                  JSON
                </button>
              </div>
            </div>

            <div className="space-y-2">
              {sendReport.results.map((result) => (
                <div
                  key={result.group_id}
                  className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm text-gray-700 dark:text-gray-200"
                >
                  <div className="font-medium">{result.title}</div>
                  <div>ID: {result.group_id}</div>
                  <div>Status: {result.success ? 'Trimis' : 'Eșuat'}</div>
                  {!result.success && result.error && <div>Eroare: {result.error}</div>}
                </div>
              ))}
            </div>
          </div>
        )}
      </section>
    </div>
  );
}

function ReadGroupCard({ group }: { group: TelegramReadGroupReport }) {
  return (
    <div className="rounded-md border border-gray-200 dark:border-gray-700 p-3">
      <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">
        {group.title} ({group.group_id})
      </div>
      <div className="text-xs text-gray-500 dark:text-gray-400 mb-2">Mesaje: {group.messages_count}</div>

      {group.messages.length === 0 ? (
        <div className="text-sm text-gray-500 dark:text-gray-400">Fără mesaje în fereastra curentă.</div>
      ) : (
        <div className="space-y-2">
          {group.messages.map((message) => (
            <div key={`${group.group_id}-${message.message_id}`} className="rounded bg-gray-50 dark:bg-gray-800 px-2 py-1.5 text-sm">
              <div className="text-xs text-gray-500 dark:text-gray-400">
                #{message.message_id} | {message.from} | {message.date ? REPORT_DATE_FORMAT.format(new Date(message.date)) : 'fără dată'}
              </div>
              <div className="text-gray-800 dark:text-gray-100 whitespace-pre-wrap">{message.text || '[media]'}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function parseGroupsText(text: string): TelegramGroup[] {
  const lines = text
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.length > 0);

  const groups: TelegramGroup[] = [];
  const seen = new Set<string>();

  lines.forEach((line) => {
    const [idRaw, ...titleParts] = line.split('|');
    const id = (idRaw ?? '').trim();
    if (id === '' || seen.has(id)) {
      return;
    }

    const titleRaw = titleParts.join('|').trim();
    groups.push({
      id,
      title: titleRaw === '' ? `Group ${id}` : titleRaw,
    });
    seen.add(id);
  });

  return groups;
}

function toGroupText(groups: TelegramGroup[]): string {
  return groups.map((group) => `${group.id}|${group.title}`).join('\n');
}

function toCsv(rows: string[][]): string {
  return rows
    .map((row) => row.map((value) => escapeCsv(value)).join(','))
    .join('\n');
}

function escapeCsv(value: string): string {
  const escaped = value.replaceAll('"', '""');
  return `"${escaped}"`;
}

function downloadBlob(filename: string, blob: Blob): void {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}
