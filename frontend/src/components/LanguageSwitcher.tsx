import { useTranslation } from 'react-i18next';
import { Globe } from 'lucide-react';

export default function LanguageSwitcher() {
  const { i18n } = useTranslation();

  const handleLanguageChange = (lng: string) => {
    i18n.changeLanguage(lng);
  };

  return (
    <div style={{ position: 'relative', display: 'inline-flex', alignItems: 'center' }}>
      <Globe size={16} color="#a1a1aa" style={{ marginRight: '6px' }} />
      <select
        value={i18n.language.startsWith('es') ? 'es' : 'en'}
        onChange={(e) => handleLanguageChange(e.target.value)}
        style={{
          background: 'transparent',
          border: 'none',
          color: '#e4e4e7',
          fontSize: '13px',
          cursor: 'pointer',
          outline: 'none',
          appearance: 'none',
          paddingRight: '12px'
        }}
      >
        <option value="es" style={{ color: '#000' }}>Español</option>
        <option value="en" style={{ color: '#000' }}>English</option>
      </select>
    </div>
  );
}
