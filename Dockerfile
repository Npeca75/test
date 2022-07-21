FROM npeca75/lnms_base:81

COPY private.patch /tmp/private.patch

# ==> Prepare user/dirs
RUN cd / \
 && useradd librenms -d /opt/librenms -M -s /bin/bash \
 && usermod -a -G librenms www-data \
 && mkdir -p /opt/librenms \
 && mkdir -p /config \
 && chown -R librenms:librenms /opt/librenms \
 && chmod 771 /opt/librenms \
 && sudo -u librenms git clone --depth=1 https://github.com/librenms/librenms.git /opt/librenms \
 && cd /opt/librenms \
 && sudo -u librenms git apply /tmp/private.patch \
 && sudo -u librenms ./scripts/composer_wrapper.php install --no-dev \
 && ln -s /opt/librenms/lnms /usr/bin/lnms \
 && mkdir -p /etc/bash_completion.d \
 && cp /opt/librenms/misc/lnms-completion.bash /etc/bash_completion.d/ \
 && cp /opt/librenms/misc/librenms.logrotate /etc/logrotate.d/librenms \
 && cp /opt/librenms/librenms.nonroot.cron /etc/cron.d/librenms \
 && setfacl -d -m g::rwx /opt/librenms/rrd /opt/librenms/logs /opt/librenms/bootstrap/cache/ /opt/librenms/storage/ \
 && setfacl -R -m g::rwx /opt/librenms/rrd /opt/librenms/logs /opt/librenms/bootstrap/cache/ /opt/librenms/storage/ \
 && rm -rf .git \
    html/plugins/Test \
    html/plugins/Weathermap/.git \
    html/plugins/Weathermap/configs \
    doc/ \
    tests/ \
    /tmp/*


# ===> ENV Block
ENV APP_URL=/ \
 DB_HOST=169.254.255.254 \
 DB_PORT=3306 \
 DB_DATABASE=librenms.d \
 DB_USERNAME=librenms \
 DB_PASSWORD=librenms123 \
 LIBRENMS_USER=librenms \
 APP_KEY=base64:eXpbWh1Pxv74lOYop7Qmk1vdChCxB33Csq1qrju8V1k= \
 NODE_ID=61ec37deb7b95 \
 DEF_IP=169.254.255.254 \
 HTPASSWD=Monitoring:{SHA}y9YdYTU/SdxKN53tgn311TxPJ28= \
 NMS_ROLE=standalone

MAINTAINER Peca Nesovanovic <peca.nesovanovic@sattrakt.com>
EXPOSE 162/tcp 162/udp 11514/tcp 11514/udp 18000/tcp
ENTRYPOINT ["tini", "--"]
CMD ["/opt/librenms/private/startup.sh"]