FROM nginxinc/nginx-unprivileged:1.25-alpine

USER root

# Cài đặt OpenSSL để tạo chứng chỉ HTTPS tự ký (Self-signed SSL)
# và cấu hình quyền thư mục log cho non-root user (UID 101 - nginx)
RUN apk add --no-cache openssl \
    && mkdir -p /etc/nginx/certs /var/log/nginx \
    && rm -f /var/log/nginx/access.log /var/log/nginx/error.log \
    && touch /var/log/nginx/access.log /var/log/nginx/error.log \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/certs/server.key \
        -out /etc/nginx/certs/server.crt \
        -subj "/C=VN/ST=ThaiNguyen/L=ThaiNguyen/O=ICTU/OU=IT/CN=dtc241200004.local" \
    && chown -R 101:101 /etc/nginx/certs /var/log/nginx \
    && chmod 600 /etc/nginx/certs/server.key \
    && chmod 644 /etc/nginx/certs/server.crt

COPY default.conf /etc/nginx/conf.d/default.conf

# Chạy container dưới quyền non-root user (nginx - UID 101)
USER 101

EXPOSE 8080 8443 8081
