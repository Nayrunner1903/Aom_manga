
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
                    docker build --progress=plain -t manga-laravel:uat .
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

        stage('Test Windows UAT Agent') {
    agent {
        label 'windows-uat'
    }
    steps {
        bat '''
            docker version
            docker ps --filter "name=manga-uat-"
        '''
    }
}
    }
}

