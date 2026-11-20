import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Firebase: فایل google-services.json در گیت نیست؛ در GitHub Actions از Secret ساخته می‌شود
if (file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}

// کلید امضا: فقط از android/key.properties (در گیت نیست؛ در GitHub Actions از Secrets ساخته می‌شود)
val keyProps = Properties().apply {
    val f = rootProject.file("key.properties")
    if (f.exists()) f.inputStream().use { load(it) }
}

android {
    namespace = "ir.hamshagerdionline.hamshagerdi"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "ir.hamshagerdionline.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // Flutter 3.47 حداقل اندروید ۷ (SDK 24) را پشتیبانی می‌کند
        minSdk = maxOf(24, flutter.minSdkVersion)
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // دو اپ از یک کد: «همشاگردی» (والدین و دانش‌آموزان) و «همشاگردی معلم» (کادر مدرسه)
    flavorDimensions += "app"
    productFlavors {
        create("family") {
            dimension = "app"
            applicationId = "ir.hamshagerdionline.app"
            resValue("string", "app_name", "همشاگردی")
        }
        create("teacher") {
            dimension = "app"
            applicationId = "ir.hamshagerdionline.teacher"
            resValue("string", "app_name", "همشاگردی معلم")
        }
    }

    signingConfigs {
        create("release") {
            if (keyProps.getProperty("storeFile") != null) {
                storeFile = file(keyProps.getProperty("storeFile"))
                storePassword = keyProps.getProperty("storePassword")
                keyAlias = keyProps.getProperty("keyAlias")
                keyPassword = keyProps.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            // بدون key.properties با کلید آزمایشی امضا می‌شود (فقط برای تست روی گوشی)
            signingConfig = if (keyProps.getProperty("storeFile") != null) signingConfigs.getByName("release") else signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
