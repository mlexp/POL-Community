FROM httpd:2.4.67

RUN sed -i \
    -e 's/^#LoadModule proxy_module /LoadModule proxy_module /' \
    -e 's/^#LoadModule proxy_fcgi_module /LoadModule proxy_fcgi_module /' \
    -e 's/^#LoadModule rewrite_module /LoadModule rewrite_module /' \
    /usr/local/apache2/conf/httpd.conf \
    && printf '\nInclude conf/extra/site.conf\n' \
       >> /usr/local/apache2/conf/httpd.conf

COPY site.conf /usr/local/apache2/conf/extra/site.conf
