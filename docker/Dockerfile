FROM tutum/apache-php
MAINTAINER cdams@outlook.fr

RUN apt-get update && apt-get install -yq wget php5-dev git && rm -rf /var/lib/apt/lists/*

# Installation du connecteur PHP pour Couchbase
RUN wget http://packages.couchbase.com/clients/c/couchbase-csdk-setup && \
      sed -i "s/REPLY = <STDIN>;/REPLY = \"y\";/g" couchbase-csdk-setup && \
      sed -i "s/apt-get -q install/apt-get -qy install/g" couchbase-csdk-setup && \
      sudo perl couchbase-csdk-setup && rm couchbase-csdk-setup && \
      pecl install couchbase && echo "extension=couchbase.so" >> /etc/php5/apache2/conf.d/20-json.ini
      
      
ADD docker.conf /etc/apache2/sites-available
RUN a2ensite docker.conf && a2enmod rewrite && service apache2 restart

# Installation de Composer      
RUN php -r "readfile('https://getcomposer.org/installer');" | php      

CMD ["/run.sh"]
      
#Tip ménage
#docker rm  $(docker ps -a  -q --filter "status=exited") && docker rmi $(docker images -q --filter "dangling=true")