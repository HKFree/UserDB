export const idPattern = /^[a-z0-9-]{1,64}$/;
export const pathPattern = /^\/[A-Za-z0-9._~/-]{0,127}$/;
export const shaPattern = /^[a-f0-9]{40}$/;
export const imagePattern = new RegExp(`^(head|base|diff)/${idPattern.source.slice(1, -1)}-(desktop|mobile)\\.png$`);

export const baseReasons = {
    'not-provided': 'srovnávací verze nebyla zadána',
    'no-flox-setup': 'verze neobsahuje prostředí Flox pro lokální web',
    'setup-failed': 'příprava aplikace nebo databáze selhala',
    'web-timeout': 'web neodpověděl do 60 sekund',
};
