import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Popup } from './Popup';
import './popup.css';

const root = document.getElementById('popup-root');
if (null === root) {
    throw new Error('#popup-root is missing from popup/index.html.');
}

createRoot(root).render(
    <StrictMode>
        <Popup />
    </StrictMode>,
);
