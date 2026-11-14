FROM wordpress:php7.4-apache

# Install ionCube Loader (WAJIB untuk theme UNBK yang ter-encode)
RUN curl -fSL https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz -o /tmp/ioncube.tar.gz \
    && tar xzf /tmp/ioncube.tar.gz -C /tmp \
    && cp /tmp/ioncube/ioncube_loader_lin_7.4.so $(php -i | grep "extension_dir" | head -1 | awk -F'=> ' '{print $NF}')/ \
    && echo "zend_extension=ioncube_loader_lin_7.4.so" > /usr/local/etc/php/conf.d/00-ioncube.ini \
    && rm -rf /tmp/ioncube*

