FROM aquarion/pdfbox:3.0@sha256:704ccd1f31a8684cb3e21d8e71018ff8533d44f92a163d068b89e795844b4e31 AS pdfbox-libs

# Sail's stock PHP 8.4 runtime (built from the sail-base service), plus what
# Libris needs to parse PDFs: Java for PDFBox, ImageMagick and Ghostscript.
FROM sail-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-jre-headless \
        imagemagick \
        ghostscript \
        php8.4-imagick \
        php8.4-intl \
    && rm -rf /var/lib/apt/lists/*

# Allow ImageMagick to read PDFs (same policy as the production image)
COPY --from=app-docker imagemagic_policy.xml /etc/ImageMagick-6/policy.xml

COPY --from=pdfbox-libs /opt/pdfbox /usr/share/java/pdfbox
