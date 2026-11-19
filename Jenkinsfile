pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Docker Image') {
            steps {
                sh '''
                    docker build -t manga-laravel:uat .
                '''
            }
        }

        stage('Verify Image') {
            steps {
                sh '''
                    docker image inspect manga-laravel:uat
                '''
            }
        }
    }
}
