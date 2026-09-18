# Build da eseguire con il contesto sulla RADICE del repository AudioCollab
# (non da questa cartella), cosi' puo' copiare src/server.js senza doverlo
# duplicare a mano prima di ogni rebuild:
#
#   docker build -f docker/audiotools/Dockerfile -t audiotools .
#
# Immagine Debian slim invece di Alpine: il pacchetto ffmpeg su Debian
# include gia' i codec necessari (incluso libmp3lame per l'encoding MP3
# usato da runTranscode in server.js); su Alpine andrebbe ricompilato da
# sorgente per avere lo stesso supporto codec.
FROM node:20-bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/audiotools/package.json ./
RUN npm install --omit=dev

COPY src/server.js ./server.js

EXPOSE 3100

# Nessuna autenticazione: il servizio si fida di chiunque possa raggiungere
# la porta 3100, quindi va sempre pubblicato solo su un indirizzo/rete
# interna (vedi docker-compose.yml e README), mai su un'interfaccia pubblica.
CMD ["node", "server.js"]
