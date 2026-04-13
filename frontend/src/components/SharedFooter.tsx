import Link from 'next/link';
import { Newspaper } from 'lucide-react';

export default function SharedFooter() {
  return (
    <footer className="border-t bg-white dark:bg-gray-900 py-10">
      <div className="container-custom">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <div className="flex items-center space-x-2 mb-3">
              <Newspaper className="h-5 w-5 text-primary-600" />
              <span className="font-bold">TeInformez.eu</span>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Știri din Romania și din lume
            </p>
          </div>

          <div>
            <h4 className="font-semibold mb-3 text-sm">Legal</h4>
            <ul className="space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
              <li><Link href="/privacy" className="hover:text-primary-600">Politica de confidențialitate</Link></li>
              <li><Link href="/terms" className="hover:text-primary-600">Termeni și condiții</Link></li>
              <li><Link href="/gdpr" className="hover:text-primary-600">GDPR</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="font-semibold mb-3 text-sm">Contact</h4>
            <ul className="space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
              <li>Email: contact@teinformez.eu</li>
            </ul>
          </div>
        </div>

        <div className="mt-8 pt-6 border-t text-center text-xs text-gray-600 dark:text-gray-400">
          <p>&copy; {new Date().getFullYear()} TeInformez.eu. Toate drepturile rezervate.</p>
        </div>
      </div>
    </footer>
  );
}
