FROM php:8.2-fpm-alpine

# Cài đặt thư viện kết nối PostgreSQL
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/cache/apk/*

# Hardening PHP: Ẩn phiên bản PHP và tắt hiển thị lỗi chi tiết ra trình duyệt
RUN { \
        echo "expose_php = Off"; \
        echo "display_errors = Off"; \
        echo "log_errors = On"; \
        echo "error_log = /proc/self/fd/2"; \
    } > /usr/local/etc/php/conf.d/hardening.ini

# Tạo non-root user (UID/GID 10001) để chạy ứng dụng an toàn
RUN addgroup -g 10001 -S appgroup \
    && adduser -u 10001 -S appuser -G appgroup

# Điều chỉnh cấu hình PHP-FPM để chạy trực tiếp bằng non-root user
RUN sed -i 's/^user = .*/;user = appuser/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = .*/;group = appgroup/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;catch_workers_output = yes/catch_workers_output = yes/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;decorate_workers_output = no/decorate_workers_output = no/g' /usr/local/etc/php-fpm.d/www.conf

# Tạo thư mục mã nguồn và thư mục log tập trung
WORKDIR /var/www/html
RUN mkdir -p /var/log/app \
    && chown -R appuser:appgroup /var/www/html /var/log/app /usr/local/etc/php-fpm.d

# Sao chép mã nguồn vào container
COPY --chown=appuser:appgroup . /var/www/html/

# Chuyển sang non-root user
USER appuser

EXPOSE 9000

CMD ["php-fpm", "-F"]

