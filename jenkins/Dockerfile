FROM jenkins/jenkins:lts

USER root

RUN mkdir -p /var/lib/apt/lists/partial \
    && chmod 644 /var/lib/apt/lists /var/lib/apt/lists/partial \
    && chmod 755 /var/lib/apt \
    && chown -R jenkins:jenkins /var/lib/apt

RUN apt-get update \
    && apt-get install -y docker-compose \
    && rm -rf /var/lib/apt/lists/*

USER jenkins